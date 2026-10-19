<?php

class Approval {
    private $pdo;

    /**
     * Administrative Forms Stages
     * Submitted (Employee) -> Checker Approval (Immediate Supervisor) -> Review Approval (Department Head) -> Grant Approval Request (Final Approval) -> Completion of Approval (Employee Request Approved)
     */
    private const LEVELS_ADMIN = [
        1 => ['role' => 3, 'label' => 'Submitted', 'status' => 'submitted'],
        2 => ['role' => 2, 'label' => 'Immediate Head Approval', 'status' => 'immediatehead_approved'],
        3 => ['role' => 4, 'label' => 'Review Approval', 'status' => 'department_reviewed'],
        4 => ['role' => 6, 'label' => 'Grant Approval Request', 'status' => 'final_approved'],
        5 => ['role' => 1, 'label' => 'Completed', 'status' => 'completed'],
    ];

    /**
     * Finance Forms Stages (Advance Payment, Request for Payment)
     * Submitted -> Immediate Head -> Process (Accounting) -> Finance Head -> Final Approver -> Completed
     */
    private const LEVELS_FINANCE = [
        1 => ['role' => 3, 'label' => 'Submitted', 'status' => 'submitted'],
        2 => ['role' => 2, 'label' => 'Immediate Head Approval', 'status' => 'immediatehead_approved'],
        3 => ['role' => 5, 'label' => 'Process Approval', 'status' => 'process_approved'],
        4 => ['role' => 8, 'label' => 'Evaluation Approval', 'status' => 'finance_reviewed'],
        5 => ['role' => 6, 'label' => 'Grant Approval Request', 'status' => 'final_approved'],
        6 => ['role' => 1, 'label' => 'Completed', 'status' => 'completed'],
    ];

    private const LEVELS_REIMBURSEMENT = [
        1 => ['role' => 3, 'label' => 'Submitted', 'status' => 'submitted'],
        2 => ['role' => 2, 'label' => 'Immediate Head Approval', 'status' => 'immediatehead_approved'],
        3 => ['role' => 9, 'label' => 'HR Verification', 'status' => 'hr_verified'],
        4 => ['role' => 5, 'label' => 'Accounting Validation', 'status' => 'process_approved'],
        5 => ['role' => 8, 'label' => 'Evaluation Approval', 'status' => 'finance_reviewed'],
        6 => ['role' => 6, 'label' => 'Grant Approval Request', 'status' => 'completed'],
    ];

    /**
     * Liquidation pipeline retains its existing role sequence.
     */
    private const LEVELS_REIMB_LIQUID = [
        1 => ['role' => 3, 'label' => 'Submitted', 'status' => 'submitted'],
        2 => ['role' => 2, 'label' => 'Immediate Head Approval', 'status' => 'immediatehead_approved'],
        3 => ['role' => 5, 'label' => 'Process Approval', 'status' => 'process_approved'],
        4 => ['role' => 9, 'label' => 'HR Verification', 'status' => 'hr_verified'],
        5 => ['role' => 8, 'label' => 'Evaluation Approval', 'status' => 'finance_reviewed'],
        6 => ['role' => 6, 'label' => 'Grant Approval Request', 'status' => 'final_approved'],
        7 => ['role' => 1, 'label' => 'Completed', 'status' => 'completed'],
    ];

    private const REIMB_LIQUID_TYPES = ['reimbursement', 'liquidation'];

    /** Form types that follow the finance pipeline. */
    private const FINANCE_TYPES = [
        'advance_payment',
        'request_for_payment',
        'reimbursement',
        'liquidation',
    ];

    public function __construct() {
        $this->pdo = new PDO(
            "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8mb4",
            $_ENV['DB_USER'],
            $_ENV['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    // ----------------------------------------------------------------
    // CREATE
    // ----------------------------------------------------------------

    public function create(array $data): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO approvals (form_id, approver_id, sequence, status, assigned_at)
            VALUES (:form_id, :approver_id, :sequence, 'pending', NOW())
        ");
        $stmt->execute([
            ':form_id' => $data['form_id'],
            ':approver_id' => $data['approver_id'],
            ':sequence' => $data['sequence'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // ----------------------------------------------------------------
    // READ
    // ----------------------------------------------------------------

    public function find(int $id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT a.*, e.full_name AS approver_name
            FROM approvals a 
            LEFT JOIN employees e ON e.id = a.approver_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByForm(int $formId): array {
        $stmt = $this->pdo->prepare("
            SELECT a.*, e.full_name AS approver_name
            FROM approvals a
            LEFT JOIN employees e ON e.id = a.approver_id
            WHERE a.form_id = ?
            ORDER BY a.sequence ASC
        ");
        $stmt->execute([$formId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function currentPending(int $formId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM approvals
            WHERE form_id = ? AND status = 'pending'
            ORDER BY sequence ASC
            LIMIT 1
        ");
        $stmt->execute([$formId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function logs(int $formId): array {
        $stmt = $this->pdo->prepare("
            SELECT al.*, e.full_name
            FROM audit_logs al
            JOIN employees e ON e.id = al.performed_by
            WHERE al.form_id = ?
            ORDER BY al.created_at ASC
        ");
        $stmt->execute([$formId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ----------------------------------------------------------------
    // PIPELINE ACTIONS
    // ----------------------------------------------------------------

    public function advance(int $id, int $actorId, int $actorRole, string $formType, string $remarks = ''): array { 
        $row = $this->find($id);

        if (!$row) {
            return ['ok' => false, 'error' => 'Approval record not found.'];
        }

        if ($row['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'This approval step is no longer pending.'];
        }

        $levels = $this->getLevels($formType);
        $maxLevel = count($levels);
        $sequence = (int) $row['sequence'];
        $levelCfg = $levels[$sequence] ?? null;

        if (!$levelCfg) {
            return ['ok' => false, 'error' => "Unknown approval sequence: {$sequence}."];
        }

        if ($actorRole !== 1 && $actorRole !== $levelCfg['role']) {
            $isMasterCoveringChecker = $actorRole === 4
                && $sequence === 2
                && !in_array($formType, self::FINANCE_TYPES, true);
            if (!$isMasterCoveringChecker) {
                return ['ok' => false, 'error' => 'You are not authorised to approve at this level.'];
            }
        }

        $this->pdo->beginTransaction();

        try {
            $this->updateStatus($id, 'approved', $actorId, $remarks);
            $this->log($row['form_id'], $sequence, 'approved', $actorId, $levelCfg['status'], $remarks);

            if ($sequence >= $maxLevel) {
                $this->setFormStatus($row['form_id'], 'completed');
            } else {
                $nextStatus = $levels[$sequence + 1]['status'] ?? 'in_approval';
                $this->setFormStatus($row['form_id'], $nextStatus);
            }

            $this->pdo->commit();
            return ['ok' => true, 'next_level' => $sequence < $maxLevel ? $sequence + 1 : null];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    public function reject(int $id, int $actorId, int $actorRole, string $formType, string $remarks): array {
        if (trim($remarks) === '') {
            return ['ok' => false, 'error' => 'A rejection reason is required.'];
        }

        $row = $this->find($id);

        if (!$row) {
            return ['ok' => false, 'error' => 'Approval record not found.'];
        }

        if ($row['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'This approval step is no longer pending.'];
        }

        $levels = $this->getLevels($formType);
        $levelCfg = $levels[$row['sequence']] ?? null;
        
        if ($actorRole !== 1 && $levelCfg && $actorRole !== $levelCfg['role']) {
            $isMasterCoveringChecker = $actorRole === 4
                && (int)$row['sequence'] === 2
                && !in_array($formType, self::FINANCE_TYPES, true);
            if (!$isMasterCoveringChecker) {
                return ['ok' => false, 'error' => 'You are not authorised to reject at this level.'];
            }
        }

        $this->pdo->beginTransaction();

        try {
            $this->updateStatus($id, 'rejected', $actorId, $remarks);
            $this->log($row['form_id'], $row['sequence'], 'rejected', $actorId, 'rejected', $remarks);
            $this->setFormStatus($row['form_id'], 'rejected');

            $this->pdo->commit();
            return ['ok' => true];

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    // ----------------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------------

    public function canAct(int $formId, int $actorId, int $actorRole, string $formType): bool {
        if ($actorRole === 1) return true;

        $pending = $this->currentPending($formId);
        if (!$pending) return false;

        $levels = $this->getLevels($formType);
        $required = $levels[$pending['sequence']]['role'] ?? null;

        if ($required !== null && $actorRole === $required) return true;
        $isMasterCoveringChecker = $actorRole === 4
            && $pending['sequence'] === 2
            && !in_array($formType, self::FINANCE_TYPES, true);
        return $isMasterCoveringChecker;
    }

    public static function pipeline(string $formType): array {
        if ($formType === 'reimbursement') return self::LEVELS_REIMBURSEMENT;
        if ($formType === 'liquidation') return self::LEVELS_REIMB_LIQUID;
        return in_array($formType, self::FINANCE_TYPES, true)
            ? self::LEVELS_FINANCE
            : self::LEVELS_ADMIN;
    }

    // ----------------------------------------------------------------
    // PRIVATE
    // ----------------------------------------------------------------

    private function getLevels(string $formType): array {
        if ($formType === 'reimbursement') return self::LEVELS_REIMBURSEMENT;
        if ($formType === 'liquidation') return self::LEVELS_REIMB_LIQUID;
        return in_array($formType, self::FINANCE_TYPES, true)
            ? self::LEVELS_FINANCE
            : self::LEVELS_ADMIN;
    }

    private function updateStatus(int $id, string $status, int $actorId, string $remarks): void {
        $this->pdo->prepare("
            UPDATE approvals
            SET status = :status, remarks = :remarks, approved_at = NOW()
            WHERE id = :id
        ")->execute([':status' => $status, ':remarks' => $remarks, ':id' => $id]);
    }

    private function insertLevel(int $formId, int $level): void {
        $this->pdo->prepare("
            INSERT INTO approvals (form_id, status, sequence, assigned_at, approver_id)
            SELECT form_id, 'pending', :level, NOW(), approver_id
            FROM approvals
            WHERE form_id = :form_id
            LIMIT 1
        ")->execute([':level' => $level, ':form_id' => $formId]);
    }

    private function setFormStatus(int $formId, string $status): void {
        $this->pdo->prepare("UPDATE forms SET status = ? WHERE id = ?")
             ->execute([$status, $formId]);
    }

    private function log(int $formId, int $sequence, string $action, int $actorId, string $resultStatus, string $remarks): void {
        $this->pdo->prepare("
            INSERT INTO audit_logs (performed_by, action, entity_type, entity_id, old_values, new_values, ip_address)
            VALUES (:actor, :action, 'form', :form_id, NULL, :new_vals, :ip)
        ")->execute([
            ':actor' => $actorId,
            ':action' => $action,
            ':form_id' => $formId,
            ':new_vals' => json_encode(['status' => $resultStatus, 'remarks' => $remarks, 'sequence' => $sequence]),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
