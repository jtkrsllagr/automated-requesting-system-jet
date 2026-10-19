<?php
class FormController {
    private array $typeMap = [
        'advance-payment' => 'advance_payment',
        'overtime-authorization' => 'overtime_authorization',
        'request-for-payment' => 'request_for_payment',
        'leave-application' => 'leave_application',
        'reimbursement' => 'reimbursement',
        'liquidation' => 'liquidation',
        'vehicle-request' => 'vehicle_request',
        'overtime' => 'overtime_authorization',
        'request-payment' => 'request_for_payment',
        'leave' => 'leave_application',
    ];

    private array $fields = [
        'advance_payment' => ['purpose', 'payment_type', 'payee', 'date'],
        'overtime_authorization' => ['employee_name', 'department', 'request_date'],
        'request_for_payment' => ['payee', 'payment_type', 'purpose', 'date'],
        'leave_application' => ['leave_type', 'from_date', 'to_date', 'payment_term'],
        'reimbursement' => ['employee_name', 'department', 'request_date'],
        'liquidation' => ['employee_name', 'department', 'request_date'],
        'vehicle_request' => ['car_available', 'employee_name', 'date', 'trip_type'],
    ];

    private const ADMIN_APPROVER_COVERS = [2, 4, 6];

    private const ADMIN_APPROVER_STANDIN_COVERAGE = [
        'leave_application' => [4],
        'overtime_authorization' => [4],
    ];

    private const FINANCE_HEAD_ROLE = 8;
    private const HR_VERIFIER_ROLE = 9;
    private const ADMIN_APPROVER_ROLE = 7;
    private const REIMB_LIQUID_TYPES = ['reimbursement', 'liquidation'];
    private const CHECK_VOUCHER_FORM_TYPES = [
        'advance_payment',
        'request_for_payment',
        'reimbursement',
        'liquidation',
    ];
    private const APPROVED_AMOUNT_FORM_TYPES = [
        'advance_payment',
        'request_for_payment',
        'reimbursement',
    ];

    private function adminApproverStandsInFor(string $formType, int $stageRoleId): bool {
        $covered = self::ADMIN_APPROVER_STANDIN_COVERAGE[$formType] ?? [];
        return in_array($stageRoleId, $covered, true);
    }

    // MasterApprover (role 4) may also act as Checker (role 2) on any form type.
    private function masterApproverStandsInFor(string $formType, int $stageRoleId): bool {
        return $stageRoleId === 2;
    }

    private function roleSatisfiesStage(int $actorRole, int $requiredRole, string $formType = ''): bool {
        if ($actorRole === $requiredRole) return true;
        if ($actorRole === 7 && in_array($requiredRole, self::ADMIN_APPROVER_COVERS, true)) return true;
        if ($actorRole === 5 && $requiredRole === 9 && $formType === 'liquidation') return true;
        if ($actorRole === 4 && $this->masterApproverStandsInFor($formType, $requiredRole)) return true;
        return false;
    }

    private const FORM_CATEGORIES = [
        'admin' => ['overtime_authorization', 'leave_application', 'vehicle_request'],
        'finance' => ['advance_payment', 'request_for_payment'],
        'reimb_liquid' => ['reimbursement', 'liquidation'],
    ];

    private const PIPELINE_ADMIN = [
        'submit' => ['sequence' => 1, 'from' => 'draft', 'to' => 'submitted', 'role_id' => 3, 'label' => 'Submitted'],
        'checker-approval'=> ['sequence' => 2, 'from' => 'submitted', 'to' => 'immediatehead_approved','role_id' => 2, 'label' => 'Immediate Head Approval'],
        'review-approval' => ['sequence' => 3, 'from' => 'immediatehead_approved','to' => 'department_reviewed',  'role_id' => 4, 'label' => 'Review Approval'],
        'grant-approval' => ['sequence' => 4, 'from' => 'department_reviewed',  'to' => 'completed',            'role_id' => 6, 'label' => 'Grant Approval Request'],
    ];

    private const PIPELINE_VEHICLE_REQUEST = [
        'submit' => ['sequence' => 1, 'from' => 'draft', 'to' => 'submitted', 'role_id' => 3, 'label' => 'Request Submitted'],
        'checker-approval' => ['sequence' => 2, 'from' => 'submitted', 'to' => 'immediatehead_approved', 'role_id' => 2, 'label' => 'Supervisor Verification'],
        'review-approval' => ['sequence' => 3, 'from' => 'immediatehead_approved', 'to' => 'department_reviewed', 'role_id' => 2, 'label' => 'Admin Evaluation'],
        'grant-approval' => ['sequence' => 4, 'from' => 'department_reviewed', 'to' => 'completed', 'role_id' => 7, 'label' => 'Final Admin Approval'],
    ];

    private const PIPELINE_FINANCE = [
        'submit' => ['sequence' => 1, 'from' => 'draft', 'to' => 'submitted', 'role_id' => 3, 'label' => 'Submitted'],
        'checker-approval' => ['sequence' => 2, 'from' => 'submitted', 'to' => 'immediatehead_approved', 'role_id' => 2, 'label' => 'Immediate Head Approval'],
        'process-approval' => ['sequence' => 3, 'from' => 'immediatehead_approved','to' => 'process_approved', 'role_id' => 5, 'label' => 'Process Approval'],
        'evaluation-approval' => ['sequence' => 4, 'from' => 'process_approved', 'to' => 'finance_reviewed', 'role_id' => 8, 'label' => 'Evaluation Approval'],
        'grant-approval' => ['sequence' => 5, 'from' => 'finance_reviewed', 'to' => 'completed', 'role_id' => 6, 'label' => 'Grant Approval Request'],
    ];

    private const PIPELINE_REIMBURSEMENT = [
        'submit' => ['sequence' => 1, 'from' => 'draft', 'to' => 'submitted', 'role_id' => 3, 'label' => 'Submitted'],
        'checker-approval' => ['sequence' => 2, 'from' => 'submitted', 'to' => 'immediatehead_approved', 'role_id' => 2, 'label' => 'Immediate Head Approval'],
        'hr-verification' => ['sequence' => 3, 'from' => 'immediatehead_approved', 'to' => 'process_approved', 'role_id' => 9, 'label' => 'HR Verification'],
        'process-approval' => ['sequence' => 4, 'from' => 'process_approved', 'to' => 'process_approved', 'role_id' => 5, 'label' => 'Accounting Validation'],
        'evaluation-approval' => ['sequence' => 5, 'from' => 'process_approved', 'to' => 'finance_reviewed', 'role_id' => 8, 'label' => 'Evaluation Approval'],
        'grant-approval' => ['sequence' => 6, 'from' => 'finance_reviewed', 'to' => 'completed', 'role_id' => 6, 'label' => 'Grant Approval Request'],
    ];

    private const PIPELINE_REIMB_LIQUID = [
        'submit' => ['sequence' => 1, 'from' => 'draft', 'to' => 'submitted', 'role_id' => 3, 'label' => 'Submitted'],
        'checker-approval' => ['sequence' => 2, 'from' => 'submitted', 'to' => 'immediatehead_approved','role_id' => 2, 'label' => 'Immediate Head Approval'],
        // Liquidation retains its existing combined HR and Accounting stage.
        'process-approval' => ['sequence' => 3, 'from' => 'immediatehead_approved', 'to' => 'process_approved', 'role_id' => 9, 'label' => 'Process (Accounting Checking)'],
        'evaluation-approval' => ['sequence' => 4, 'from' => 'process_approved', 'to' => 'finance_reviewed', 'role_id' => 8, 'label' => 'Evaluation Approval'],
        'grant-approval' => ['sequence' => 5, 'from' => 'finance_reviewed', 'to' => 'completed', 'role_id' => 6, 'label' => 'Grant Approval Request'],
    ]; 

    private function isFinalHrDepartmentRequest(string $formType, ?string $department): bool {
        return in_array($formType, ['leave_application', 'overtime_authorization'], true)
            && preg_match('/\b(management|human\s+resources|hr|admin(?:istration)?)\b/', strtolower(trim((string) $department))) === 1;
    }

    private function isSupervisorFinalHrDepartmentRequest(string $formType, ?string $department): bool {
        return in_array($formType, ['leave_application', 'overtime_authorization'], true)
            && preg_match('/\b(accounting|information\s+technology|it|finance|logistics)\b|(?<![a-z-])sales\b|\bpre-sales\s*\(engineer\)|\bpost-sales\s*\(engineer\)/i', trim((string) $department)) === 1;
    }

    private function getPipeline(
        string $formType,
        ?string $submitterDepartment = null,
        ?int $submitterId = null,
        ?int $formId = null
    ): array {
        if ($formType === 'vehicle_request') {
            $pipeline = self::PIPELINE_VEHICLE_REQUEST;
            $hasSupervisorStage = $formId !== null
                ? $this->hasVehicleSupervisorApproval($formId)
                : ($submitterId !== null && $this->hasActiveVehicleSupervisor($submitterId));
            if (!$hasSupervisorStage) {
                $pipeline['review-approval']['from'] = 'submitted';
                unset($pipeline['checker-approval']);
            }
            return $pipeline;
        }
        if ($this->isFinalHrDepartmentRequest($formType, $submitterDepartment)) {
            return [
                'submit' => ['sequence' => 1, 'from' => 'draft', 'to' => 'submitted', 'role_id' => 3, 'label' => 'Request Submitted'],
                'grant-approval' => ['sequence' => 2, 'from' => 'submitted', 'to' => 'completed', 'role_id' => 6, 'label' => 'Final Authorization'],
            ];
        }
        if ($this->isSupervisorFinalHrDepartmentRequest($formType, $submitterDepartment)) {
            return [
                'submit' => ['sequence' => 1, 'from' => 'draft', 'to' => 'submitted', 'role_id' => 3, 'label' => 'Request Submitted'],
                'checker-approval' => ['sequence' => 2, 'from' => 'submitted', 'to' => 'immediatehead_approved', 'role_id' => 2, 'label' => 'Supervisor Verification'],
                'grant-approval' => ['sequence' => 3, 'from' => 'immediatehead_approved', 'to' => 'completed', 'role_id' => 6, 'label' => 'Final Authorization'],
            ];
        }
        if ($formType === 'reimbursement') return self::PIPELINE_REIMBURSEMENT;
        if ($formType === 'liquidation') return self::PIPELINE_REIMB_LIQUID;
        if (in_array($formType, self::FORM_CATEGORIES['finance'], true)) return self::PIPELINE_FINANCE;
        return self::PIPELINE_ADMIN;
    }

    private function hasActiveVehicleSupervisor(int $submitterId): bool {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM employees requester
             JOIN employees supervisor
               ON supervisor.id = requester.supervisor_id
               OR supervisor.id = requester.supervisor_id_2
             WHERE requester.id = ? AND supervisor.is_active = 1'
        );
        $stmt->execute([$submitterId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function hasVehicleSupervisorApproval(int $formId): bool {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM approvals
             WHERE form_id = ? AND sequence = 2'
        );
        $stmt->execute([$formId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function getEmployeeDepartment(\PDO $pdo, int $employeeId): ?string {
        $stmt = $pdo->prepare('SELECT department FROM employees WHERE id = ?');
        $stmt->execute([$employeeId]);
        $department = $stmt->fetchColumn();
        return $department === false ? null : (string) $department;
    }

    private function employeeHasAssignedImmediateHead(\PDO $pdo, int $employeeId): bool {
        $stmt = $pdo->prepare('SELECT supervisor_id FROM employees WHERE id = ? AND is_active = 1');
        $stmt->execute([$employeeId]);
        $supervisorId = $stmt->fetchColumn();
        return $supervisorId !== false && (int) $supervisorId > 0;
    }

    private function normalizeReimbursementApprovalTrail(\PDO $pdo, int $formId): ?string {
        $legacyAccountingRows = $pdo->prepare(
            'SELECT COUNT(*) FROM approvals a
             JOIN employees e ON e.id = a.approver_id
             WHERE a.form_id = ? AND a.sequence = 3 AND e.role_id = 5'
        );
        $legacyAccountingRows->execute([$formId]);
        if ((int) $legacyAccountingRows->fetchColumn() === 0) {
            return null;
        }

        $pdo->beginTransaction();

        try {
            $formStmt = $pdo->prepare(
                'SELECT form_type, status FROM forms WHERE id = ? FOR UPDATE'
            );
            $formStmt->execute([$formId]);
            $form = $formStmt->fetch(PDO::FETCH_ASSOC);
            if (!$form || $form['form_type'] !== 'reimbursement') {
                $pdo->commit();
                return null;
            }

            $legacyAccountingRows = $pdo->prepare(
                'SELECT COUNT(*) FROM approvals a
                 JOIN employees e ON e.id = a.approver_id
                 WHERE a.form_id = ? AND a.sequence = 3 AND e.role_id = 5'
            );
            $legacyAccountingRows->execute([$formId]);

            if ((int) $legacyAccountingRows->fetchColumn() > 0) {
                $pdo->prepare(
                    'UPDATE approvals SET sequence = sequence + 100
                     WHERE form_id = ? AND sequence >= 4'
                )->execute([$formId]);
                $pdo->prepare(
                    'UPDATE approvals SET sequence = sequence - 99
                     WHERE form_id = ? AND sequence >= 104'
                )->execute([$formId]);
                $pdo->prepare(
                    'UPDATE approvals a
                     JOIN employees e ON e.id = a.approver_id
                     SET a.sequence = 4
                     WHERE a.form_id = ? AND a.sequence = 3 AND e.role_id = 5'
                )->execute([$formId]);

                $hrApproved = $pdo->prepare(
                    "SELECT COUNT(*) FROM approvals a
                     JOIN employees e ON e.id = a.approver_id
                     WHERE a.form_id = ? AND a.sequence = 3
                       AND a.status = 'approved' AND e.role_id = 9"
                );
                $hrApproved->execute([$formId]);
                if ((int) $hrApproved->fetchColumn() > 0) {
                    $accountingPending = $pdo->prepare(
                        "SELECT COUNT(*) FROM approvals a
                         JOIN employees e ON e.id = a.approver_id
                         WHERE a.form_id = ? AND a.sequence = 4
                           AND a.status = 'pending' AND e.role_id = 5"
                    );
                    $accountingPending->execute([$formId]);
                    $accountingApproved = $pdo->prepare(
                        "SELECT COUNT(*) FROM approvals a
                         JOIN employees e ON e.id = a.approver_id
                         WHERE a.form_id = ? AND a.sequence = 4
                           AND a.status = 'approved' AND e.role_id = 5"
                    );
                    $accountingApproved->execute([$formId]);

                    $nextStatus = (int) $accountingPending->fetchColumn() > 0
                        ? 'process_approved'
                        : ((int) $accountingApproved->fetchColumn() > 0 ? 'process_approved' : null);
                    if ($nextStatus !== null) {
                        $pdo->prepare(
                            'UPDATE forms SET status = ?, updated_at = NOW() WHERE id = ?'
                        )->execute([$nextStatus, $formId]);
                        $form['status'] = $nextStatus;
                    }
                }
            } else {
                $pdo->commit();
                return $form['status'];
            }

            $pdo->commit();
            return $form['status'];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function index(string $slug): void {
        $type = $this->resolveType($slug);
        $userId = $_SESSION['user_id'];
        $roleId = $_SESSION['role_id'];

        if ($roleId === 1 || $roleId !== 3) {
            $stmt = db()->prepare(
                'SELECT f.id, f.status, f.created_at,
                    COALESCE(f.check_voucher, JSON_UNQUOTE(JSON_EXTRACT(f.data, "$.check_voucher"))) AS check_voucher,
                    e.full_name /** **/
                FROM forms f JOIN employees e ON e.id = f.submitted_by
                WHERE f.form_type = ? ORDER BY f.created_at DESC LIMIT 50'
            );
            $stmt->execute([$type]);
        } else {
            $stmt = db()->prepare(
                'SELECT f.id, f.status, f.created_at,
                    COALESCE(f.check_voucher, JSON_UNQUOTE(JSON_EXTRACT(f.data, "$.check_voucher"))) AS check_voucher,
                    e.full_name
                FROM forms f JOIN employees e ON e.id = f.submitted_by
                WHERE f.form_type = ? AND f.submitted_by = ?
                ORDER BY f.created_at DESC LIMIT 30'
            );
            $stmt->execute([$type, $userId]);
        }

        $forms = $stmt->fetchAll();
        $formType = $type;
        $pageTitle = \App\Helpers\FormLabels::get($type);
        $this->render('forms/list', compact('forms', 'formType', 'slug', 'pageTitle'));
    }

    public function create(string $slug): void {
        $type = $this->resolveType($slug);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->store($type, $slug);
            return;
        }

        $fields = $this->fields[$type];
        $formType = $type;
        $noSuffix = ['list', 'show', 'request_for_payment'];
        $viewName = in_array($type, $noSuffix) ? $type : "{$type}_form";
        $pageTitle = \App\Helpers\FormLabels::get($type);
        $departments = db()->query('SELECT name FROM departments ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
        $currentUser = $_SESSION['user_name'] ?? '';
        $currentDept = $_SESSION['department'] ?? '';
        $breadcrumbs = [['label' => 'New Request'], ['label' => $pageTitle]];

        $this->render("forms/{$viewName}", compact(
            'fields', 'formType', 'slug', 'pageTitle',
            'departments', 'currentUser', 'currentDept', 'breadcrumbs'
        ));
    }

    public function show(int $id): void {
        $form = $this->findForm($id);
        if ($form['form_type'] === 'vehicle_request') {
            \App\Services\VehicleRequestWorkflowService::normalizePendingRequests($id);
            $form = $this->findForm($id);
        }
        \App\Services\PendingCountService::normalizeLegacyFinalHrDepartmentRequests($id);
        $form = $this->findForm($id);
        if ($form['form_type'] === 'reimbursement') {
            $normalizedStatus = $this->normalizeReimbursementApprovalTrail(db(), $id);
            if ($normalizedStatus !== null) {
                $form['status'] = $normalizedStatus;
            }
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $roleId = (int) ($_SESSION['role_id'] ?? 0);
        $isOwner = $roleId === 1 || (int) $form['submitted_by'] === $userId;
        if ($form['status'] === 'draft' && ($isOwner || $this->canEditAsProcessApprover($form))) {
            header('Location: ' . url('forms/' . $id . '/edit'));
            exit;
        }

        $approvals = db()->prepare(
            'SELECT a.*, e.full_name, e.role_id AS approver_role_id FROM approvals a
            JOIN employees e ON e.id = a.approver_id
            WHERE a.form_id = ? ORDER BY a.sequence'
        );
        $approvals->execute([$id]);
        $approvalSteps = $approvals->fetchAll();

        $pipeline = $this->getPipeline($form['form_type'], $form['submitter_department'] ?? null, (int) $form['submitted_by'], (int) $form['id']);
        $pendingSequences = array_values(array_unique(array_map(
            static fn(array $step): int => (int) $step['sequence'],
            array_filter($approvalSteps, static fn(array $step): bool => $step['status'] === 'pending')
        )));
        sort($pendingSequences);

        $nextAction = null;
        if (!empty($pendingSequences)) {
            foreach ($pipeline as $action => $step) {
                if ((int) $step['sequence'] === $pendingSequences[0]) {
                    $nextAction = $action;
                    break;
                }
            }
        }
        if ($nextAction === null) {
            foreach ($pipeline as $action => $step) {
                if ($step['from'] !== '*' && $step['from'] === $form['status']) {
                    $nextAction = $action;
                    break;
                }
            }
        }

        $canAct = $this->canActOnForm($form, $approvalSteps);

        $userId = (int) $_SESSION['user_id'];
        $roleId = (int) $_SESSION['role_id'];
        $isOwner = $roleId === 1 || (int) $form['submitted_by'] === $userId;
        $editableStatuses = ['draft', 'submitted', 'rejected'];
        // Same rule as edit()/update(): the owner while in an editable status,
        // OR the AcquisitionChecker while the form sits at their Process
        // (Accounting Checking) stage.
        $canEdit = ($isOwner && in_array($form['status'], $editableStatuses, true))
            || $this->canEditAsProcessApprover($form);

        $data = json_decode($form['data'], true) ?? [];
        $formLabel = \App\Helpers\FormLabels::all();
        $typeLabel = \App\Helpers\FormLabels::get($form['form_type']);
        $pageTitle = $typeLabel . ' #' . $id;
        $breadcrumbs = [
            ['label' => $typeLabel, 'url' => url('forms/' . (array_search($form['form_type'], $this->typeMap) ?: 'list'))],
            ['label' => '#' . $id],
        ];

        $this->render('forms/show', compact(
            'form', 'approvalSteps', 'canAct', 'canEdit', 'data',
            'pageTitle', 'nextAction', 'breadcrumbs'
        ));
    }

    public function approve(int $id, string $action): void {
        $this->processApproval($id, $action);
    }

    public function reject(int $id): void {
        \App\Helpers\Csrf::verify();

        $form = $this->findForm($id);
        if ($form['form_type'] === 'vehicle_request') {
            \App\Services\VehicleRequestWorkflowService::normalizePendingRequests($id);
            $form = $this->findForm($id);
        }
        $remarks = trim($_POST['remarks'] ?? '');
        $userId = (int) $_SESSION['user_id'];
        $roleId = (int) $_SESSION['role_id'];
        if ($form['form_type'] === 'reimbursement'
            && $roleId === self::HR_VERIFIER_ROLE
            && (int) $form['submitted_by'] === $userId
        ) {
            $_SESSION['error'] = 'A different HR Verifier must process this reimbursement request.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }
        if ($roleId === 6) {
            \App\Services\PendingCountService::normalizeLegacyFinalHrDepartmentRequests($id);
            $form = $this->findForm($id);
        }

        $allowedRoles = [1, 2, 4, 5, 6, 7, 8, 9];
        if (!in_array($roleId, $allowedRoles, true)) {
            $_SESSION['error'] = 'You are not authorised to reject this form.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }

        if ($form['form_type'] === 'vehicle_request') {
            $activeVehicleStep = db()->prepare(
                "SELECT MIN(sequence) FROM approvals WHERE form_id = ? AND status = 'pending'"
            );
            $activeVehicleStep->execute([$id]);
            $activeVehicleSequence = (int) $activeVehicleStep->fetchColumn();
            $requiredVehicleRole = match ($activeVehicleSequence) {
                2, 3 => 2,
                4 => 7,
                default => 0,
            };
            $assignedVehicleStep = db()->prepare(
                "SELECT id FROM approvals
                 WHERE form_id = ? AND sequence = ? AND approver_id = ? AND status = 'pending'
                 LIMIT 1"
            );
            $assignedVehicleStep->execute([$id, $activeVehicleSequence, $userId]);
            $isAdminApproverImmediateHead = $activeVehicleSequence === 2
                && $roleId === self::ADMIN_APPROVER_ROLE;
            if ($requiredVehicleRole === 0
                || ($roleId !== $requiredVehicleRole && !$isAdminApproverImmediateHead)
                || !$assignedVehicleStep->fetch()
            ) {
                $_SESSION['error'] = 'Only the assigned approver for the current Vehicle Request stage can reject it.';
                header("Location: " . url("forms/view/{$id}"));
                exit;
            }
        }

        $isAdminApproverStandIn = $roleId === 7;
        $isMasterApproverStandIn = $roleId === 4;
        $isFinalApproverStandIn = $roleId === 6;
        $isHrVerifierStandIn = $roleId === 9;
        $isFinanceHeadStandIn = $roleId === 8;
        $isAccountingStandIn = $roleId === 5;

        if ($roleId !== 1) {
            $activeSeqStmt = db()->prepare(
                'SELECT MIN(sequence) FROM approvals WHERE form_id = ? AND status = \'pending\''
            );
            $activeSeqStmt->execute([$id]);
            $activeSequence = $activeSeqStmt->fetchColumn();

            if ($activeSequence === false) {
                $_SESSION['error'] = 'No pending approval step found for this form.';
                header("Location: " . url("forms/view/{$id}"));
                exit;
            }

            if ($form['form_type'] === 'reimbursement'
                && $roleId === self::HR_VERIFIER_ROLE
                && (int) $form['submitted_by'] === $userId
                && (int) $activeSequence === 3
            ) {
                $_SESSION['error'] = 'A different HR Verifier must process this reimbursement HR Verification step.';
                header("Location: " . url("forms/view/{$id}"));
                exit;
            }

            if ($isAdminApproverStandIn || $isMasterApproverStandIn || $isFinalApproverStandIn || $isHrVerifierStandIn || $isFinanceHeadStandIn || $isAccountingStandIn) {
                $pipeline = $this->getPipeline($form['form_type'], $form['submitter_department'] ?? null, (int) $form['submitted_by'], (int) $form['id']);
                $activeStepRole = null;
                foreach ($pipeline as $pStep) {
                    if ((int)$pStep['sequence'] === (int)$activeSequence) {
                        $activeStepRole = (int) $pStep['role_id'];
                        break;
                    }
                }
                if ($activeStepRole === null || !$this->adminApproverStandsInFor($form['form_type'], $activeStepRole)) {
                    $isAdminApproverStandIn = false;
                }
                if ($activeStepRole === null || !$this->masterApproverStandsInFor($form['form_type'], $activeStepRole)) {
                    $isMasterApproverStandIn = false;
                }
                if ($activeStepRole !== 6) {
                    $isFinalApproverStandIn = false;
                }
                if ($activeStepRole !== 9) {
                    $isHrVerifierStandIn = false;
                }
                if ($activeStepRole !== 8) {
                    $isFinanceHeadStandIn = false;
                }
                $isAccountingCoSignStage = $activeStepRole === 9
                    && $form['form_type'] === 'liquidation';
                if ($activeStepRole !== 5 && !$isAccountingCoSignStage) {
                    $isAccountingStandIn = false;
                }
                if ($form['form_type'] === 'reimbursement'
                    && in_array($roleId, [5, 9], true)
                    && $roleId !== $activeStepRole
                ) {
                    $_SESSION['error'] = 'You are not authorised to reject this approval stage.';
                    header("Location: " . url("forms/view/{$id}"));
                    exit;
                }
            }

            if ($isAdminApproverStandIn || $isMasterApproverStandIn || $isFinalApproverStandIn || $isHrVerifierStandIn || $isFinanceHeadStandIn || $isAccountingStandIn) {
                if ($isAccountingStandIn || $isHrVerifierStandIn) {
                    $standInRoleId = $isAccountingStandIn ? 5 : 9;
                    $myStep = db()->prepare(
                        'SELECT a.id FROM approvals a JOIN employees e ON e.id = a.approver_id
                         WHERE a.form_id = ? AND a.sequence = ? AND a.status = \'pending\'
                         AND e.role_id = ? LIMIT 1'
                    );
                    $myStep->execute([$id, $activeSequence, $standInRoleId]);
                } else {
                    $myStep = db()->prepare(
                        'SELECT id FROM approvals WHERE form_id = ? AND sequence = ? AND status = \'pending\' LIMIT 1'
                    );
                    $myStep->execute([$id, $activeSequence]);
                }
            } else {
                $myStep = db()->prepare(
                    'SELECT id FROM approvals WHERE form_id = ? AND sequence = ? AND approver_id = ? AND status = \'pending\' LIMIT 1'
                );
                $myStep->execute([$id, $activeSequence, $userId]);
            }
            $step = $myStep->fetch(PDO::FETCH_ASSOC);

            if (!$step && $isAccountingStandIn
                && $form['form_type'] === 'liquidation'
            ) {
                $legacyHrStep = db()->prepare(
                    'SELECT a.id FROM approvals a JOIN employees e ON e.id = a.approver_id
                     WHERE a.form_id = ? AND a.sequence = ? AND a.status = \'pending\'
                     AND e.role_id = 9 LIMIT 1'
                );
                $legacyHrStep->execute([$id, $activeSequence]);
                $step = $legacyHrStep->fetch(PDO::FETCH_ASSOC);
            }

            if (!$step) {
                $_SESSION['error'] = 'You are not the assigned approver for the current stage.';
                header("Location: " . url("forms/view/{$id}"));
                exit;
            }
        }

        if (in_array($form['status'], ['completed', 'rejected'], true)) {
            $_SESSION['error'] = 'This form is already finalised.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }

        if ($remarks === '') {
            $_SESSION['error'] = 'A rejection reason is required.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            if ($roleId !== 1) {
                // Record who actually rejected it on their own row, so a
                // stand-in (AdminApprover/MasterApprover/FinalApprover) keeps
                // view access afterwards — findForm() checks approver_id = them
                // regardless of status. Other pending co-signer rows on the
                // same form (e.g. HR + Accounting co-sign) are still closed
                // out below without reassigning their attribution.
                $pdo->prepare(
                    "UPDATE approvals SET status = 'rejected', approver_id = ?, remarks = ?, approved_at = NOW()
                     WHERE id = ? AND status = 'pending'"
                )->execute([$userId, $remarks, $step['id']]);
            }

            $pdo->prepare(
                "UPDATE approvals SET status = 'rejected', remarks = ?, approved_at = NOW()
                 WHERE form_id = ? AND status = 'pending'"
            )->execute([$remarks, $id]);

            $pdo->prepare("UPDATE forms SET status = 'rejected' WHERE id = ?")->execute([$id]);

            $this->audit('form_rejected', 'form', $id,
                ['status' => $form['status']],
                ['status' => 'rejected', 'remarks' => $remarks]
            );

            $pdo->commit();
            \App\Services\RealtimeEventService::publishPendingCountChanged();
            $_SESSION['success'] = 'Form rejected successfully.';
            $this->sendRejectionNotification($id, $remarks);

        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Rejection failed. Please try again.';
        }

        header("Location: " . url("forms/view/{$id}"));
        exit;
    }

    public function mySubmissions(): void {
        $userId = (int) $_SESSION['user_id'];
        $status = $_GET['status'] ?? 'in_approval';
        $statusGroups = [
            'in_approval' => ['submitted', 'immediatehead_approved', 'process_approved', 'department_reviewed', 'finance_reviewed', 'supervisor_reviewed', 'department_checked'],
            'approved' => ['final_approved', 'completed'],
            'draft' => ['draft'],
            'rejected' => ['rejected'],
        ];
        if (!array_key_exists($status, $statusGroups)) {
            $status = 'in_approval';
        }
        $statuses = $statusGroups[$status];
        $statusPlaceholders = implode(', ', array_fill(0, count($statuses), '?'));
        $stmt = db()->prepare(
            "SELECT f.id, f.form_type, f.status, f.created_at, e.full_name,
                    (SELECT MIN(sequence) FROM approvals WHERE form_id = f.id AND status = 'pending') AS current_step
            FROM forms f JOIN employees e ON e.id = f.submitted_by
            WHERE f.submitted_by = ? AND f.status IN ({$statusPlaceholders})
            ORDER BY f.created_at DESC, f.id DESC"
        );
        $stmt->execute(array_merge([$userId], $statuses));
        $forms = $stmt->fetchAll();
        $formLabel = \App\Helpers\FormLabels::all();
        $statusLabel = [
            'in_approval' => 'In Approval',
            'approved' => 'Approved',
            'draft' => 'Drafts',
            'rejected' => 'Rejected',
        ][$status];
        $pageTitle = 'My Submissions';
        $breadcrumbs = [['label' => 'My Submissions'], ['label' => $statusLabel]];

        define('BASE_LOADED', true);
        ob_start();
        require __DIR__ . '/../../views/forms/my_submissions.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../views/layouts/base.php';
    }

    public function allRequests(): void {
        $roleId = (int) ($_SESSION['role_id'] ?? 0);
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if (in_array($roleId, [1, 6, 7], true)) {
            $statusFilter = $roleId === 1
                ? 'WHERE f.status NOT IN ("draft","cancelled")'
                : '';
            $params = [];
        } else {
            $statusFilter = 'WHERE EXISTS (
                SELECT 1 FROM approvals process_member
                WHERE process_member.form_id = f.id
                  AND process_member.approver_id = ?
            )';
            $params = [$userId];
        }
        $stmt = db()->prepare(
            'SELECT f.id, f.form_type, f.status, f.created_at, e.full_name, e.department
            FROM forms f JOIN employees e ON e.id = f.submitted_by
            ' . $statusFilter . '
            ORDER BY f.created_at DESC'
        );
        $stmt->execute($params);
        $forms = $stmt->fetchAll();
        $formLabel = \App\Helpers\FormLabels::all();
        $pageTitle = 'All Requests';
        $breadcrumbs = [['label' => 'All Requests']];

        define('BASE_LOADED', true);
        ob_start();
        require __DIR__ . '/../../views/forms/all_requests.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../views/layouts/base.php';
    }

    /**
     * GET /requests/export?format=csv|xlsx|docx|pdf — privileged-role download
     * of every completed form request, in the chosen format. Each row/section
     * includes the full submitted form data (not just summary columns) plus
     * the approval trail, not just metadata.
     *
     * xlsx and docx are the well-established "HTML dressed as Office file"
     * trick — an HTML table/document served with the Excel/Word MIME type
     * and file extension. Both Excel and Word open these natively; no
     * PhpSpreadsheet/PhpWord dependency needed for what's essentially a
     * formatted data dump.
     *
     * pdf is different: there is no dependency-free way to produce a real
     * PDF binary from PHP. This renders the same content as a print-ready
     * HTML page and auto-opens the browser's print dialog so the admin can
     * "Save as PDF" — it is NOT a server-generated .pdf file. If a true
     * server-side PDF is needed later, add a library such as dompdf/dompdf
     * via Composer and swap this branch for it.
     */
    public function exportCompletedRequests(): void {
        if (!in_array((int)($_SESSION['role_id'] ?? 0), [1, 6, 7], true)) {
            $_SESSION['error'] = 'Access denied.';
            header('Location: ' . url('dashboard')); exit;
        }

        $format = strtolower($_GET['format'] ?? 'csv');
        if (!in_array($format, ['csv', 'xlsx', 'docx', 'pdf'], true)) {
            $format = 'csv';
        }

        $stmt = db()->prepare(
            'SELECT f.id, f.form_type, f.data, f.created_at, f.updated_at,
                    e.employee_code, e.full_name, e.department, e.company
             FROM forms f
             JOIN employees e ON e.id = f.submitted_by
             WHERE f.status = "completed"
             ORDER BY f.updated_at DESC'
        );
        $stmt->execute();
        $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($forms)) {
            $_SESSION['error'] = 'No completed requests to export yet.';
            header('Location: ' . url('requests'));
            exit;
        }

        // Approval trail for every exported form, fetched in one query and
        // grouped by form_id, so each export includes who acted at every
        // stage — not just the submission itself.
        $formIds = array_column($forms, 'id');
        $placeholders = implode(',', array_fill(0, count($formIds), '?'));
        $apprStmt = db()->prepare(
            "SELECT a.form_id, a.sequence, a.status, a.remarks, a.approved_at,
                    ea.full_name AS approver_name
             FROM approvals a
             JOIN employees ea ON ea.id = a.approver_id
             WHERE a.form_id IN ({$placeholders})
             ORDER BY a.form_id, a.sequence"
        );
        $apprStmt->execute($formIds);
        $approvalsByForm = [];
        foreach ($apprStmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $approvalsByForm[(int) $a['form_id']][] = $a;
        }

        $formLabel = \App\Helpers\FormLabels::all();

        // Bulk export, not tied to one record — entity_id 0 is the
        // convention this app uses for actions with no single target.
        $this->audit('requests_exported', 'form', 0, null, [
            'exported_by' => $_SESSION['user_id'],
            'format' => $format,
            'count' => count($forms),
        ]);

        if ($format === 'xlsx') {
            $this->streamExcelExport($forms, $approvalsByForm, $formLabel);
        } elseif ($format === 'docx') {
            $this->streamWordExport($forms, $approvalsByForm, $formLabel);
        } elseif ($format === 'pdf') {
            $this->streamPrintableExport($forms, $approvalsByForm, $formLabel);
        } else {
            $this->streamCsvExport($forms, $approvalsByForm, $formLabel);
        }
        exit;
    }

    // ================================================================
    // PRIVATE
    // ================================================================

    private function processApproval(int $id, string $action): void {
        \App\Helpers\Csrf::verify();

        $form = $this->findForm($id);
        if ($form['form_type'] === 'vehicle_request') {
            \App\Services\VehicleRequestWorkflowService::normalizePendingRequests($id);
            $form = $this->findForm($id);
        }
        if ((int) ($_SESSION['role_id'] ?? 0) === 6) {
            \App\Services\PendingCountService::normalizeLegacyFinalHrDepartmentRequests($id);
            $form = $this->findForm($id);
        }
        if ($form['form_type'] === 'reimbursement') {
            $normalizedStatus = $this->normalizeReimbursementApprovalTrail(db(), $id);
            if ($normalizedStatus !== null) {
                $form['status'] = $normalizedStatus;
            }
        }
        $remarks = trim($_POST['remarks'] ?? '');
        $checkVoucher = trim($_POST['check_voucher'] ?? '');
        $approvedAmount = trim($_POST['approved_amount'] ?? '');
        $userId = (int) $_SESSION['user_id'];
        $roleId = (int) $_SESSION['role_id'];
        $isAdmin = $roleId === 1;
        $isAdminApproverStandIn = $roleId === 7;

        $pipeline = $this->getPipeline($form['form_type'], $form['submitter_department'] ?? null, (int) $form['submitted_by'], (int) $form['id']);
        if (!isset($pipeline[$action])) {
            $_SESSION['error'] = "Unknown approval action: '{$action}'.";
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }

        $step = $pipeline[$action];
        if ($form['form_type'] === 'vehicle_request'
            && (
                ($action === 'review-approval' && $roleId !== 2)
                || ($action === 'grant-approval' && $roleId !== 7)
            )
        ) {
            $_SESSION['error'] = $action === 'review-approval'
                ? 'Only the Immediate Head of the Admin Department can process Admin Evaluation.'
                : 'Only an AdminApprover can process Final Admin Approval.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }

        if ($form['form_type'] === 'reimbursement'
            && $roleId === self::HR_VERIFIER_ROLE
            && (int) $form['submitted_by'] === $userId
            && (int) $step['sequence'] === 3
        ) {
            $_SESSION['error'] = 'A different HR Verifier must process this reimbursement HR Verification step.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }
        $isAdminApproverStandIn = $isAdminApproverStandIn
            && $this->adminApproverStandsInFor($form['form_type'], (int) $step['role_id']);
        $isMasterApproverStandIn = $roleId === 4
            && $this->masterApproverStandsInFor($form['form_type'], (int) $step['role_id']);
        // FinalApprover (role 6) shared queue: any Final Approver may act on a
        // pending Grant Approval row, not just the one auto-assigned to them.
        $isFinalApproverStandIn = $roleId === 6 && (int) $step['role_id'] === 6;
        // HRVerifier (role 9) shared queue: any HR Verifier may act on a
        // pending Process Approval (HR Verification) row on Reimbursement /
        // Liquidation forms, not just the one auto-assigned to them.
        $isHrVerifierStandIn = $roleId === 9 && (int) $step['role_id'] === 9;
        // FinanceHead (role 8) shared queue: any Finance Head may act on a
        // pending Evaluation Approval row, not just the one auto-assigned to them.
        $isFinanceHeadStandIn = $roleId === 8 && (int) $step['role_id'] === 8;
        // AcquisitionChecker/Accounting (role 5) shared queue: any Accounting
        // account may act on a pending Process (Accounting Checking) row,
        // not just the one auto-assigned to them.
        $isAccountingStandIn = $roleId === 5 && (
            (int) $step['role_id'] === 5
            || ((int) $step['role_id'] === 9 && $form['form_type'] === 'liquidation')
        );

        $legacyHrVerifiedStatus = $form['form_type'] === 'reimbursement'
            && $action === 'process-approval'
            && $form['status'] === 'hr_verified';
        if ($step['from'] !== '*' && $form['status'] !== $step['from'] && !$legacyHrVerifiedStatus) {
            $_SESSION['error'] = sprintf(
                "Cannot perform '%s': form is currently '%s', expected '%s'.",
                $action, $form['status'], $step['from']
            );
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }

        if (in_array($form['status'], ['completed', 'rejected'], true)) {
            $_SESSION['error'] = 'This form is already finalized.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }

        $approvalRow = db()->prepare(
            'SELECT * FROM approvals WHERE form_id = ? AND sequence = ? AND approver_id = ? AND status = \'pending\' LIMIT 1'
        );
        $approvalRow->execute([$id, $step['sequence'], $userId]);
        $approval = $approvalRow->fetch();

        if (!$approval && ($isAdmin || $isAdminApproverStandIn || $isMasterApproverStandIn || $isFinalApproverStandIn || $isHrVerifierStandIn || $isFinanceHeadStandIn || $isAccountingStandIn)) {
            $fallbackSql = 'SELECT * FROM approvals WHERE form_id = ? AND sequence = ? AND status = \'pending\'';
            $fallbackParams = [$id, $step['sequence']];
            if ($isAccountingStandIn || $isHrVerifierStandIn) {
                $fallbackRoleId = $isAccountingStandIn ? 5 : 9;
                $fallbackSql .= ' AND approver_id IN (SELECT id FROM employees WHERE role_id = ?)';
                $fallbackParams[] = $fallbackRoleId;
            }
            $fallback = db()->prepare($fallbackSql . ' LIMIT 1');
            $fallback->execute($fallbackParams);
            $approval = $fallback->fetch();

            if (!$approval && $isAccountingStandIn
                && $form['form_type'] === 'liquidation'
            ) {
                $legacyHrStep = db()->prepare(
                    'SELECT * FROM approvals WHERE form_id = ? AND sequence = ? AND status = \'pending\'
                     AND approver_id IN (SELECT id FROM employees WHERE role_id = 9) LIMIT 1'
                );
                $legacyHrStep->execute([$id, $step['sequence']]);
                $approval = $legacyHrStep->fetch();
            }
        }

        // FIX 3a: Removed stale `|| ($action === 'process-approval' && $roleId === 9)` hack
        if (!$isAdmin && !$isAdminApproverStandIn && !$isMasterApproverStandIn && !$isFinalApproverStandIn && !$isHrVerifierStandIn && !$isFinanceHeadStandIn && !$isAccountingStandIn && $action !== 'submit') {
            if (!$approval || (int)$approval['approver_id'] !== $userId) {
                $_SESSION['error'] = 'No pending approval step found for you at this stage.';
                header("Location: " . url("forms/view/{$id}"));
                exit;
            }
        }

        if (($isAdminApproverStandIn || $isMasterApproverStandIn || $isFinalApproverStandIn || $isHrVerifierStandIn || $isFinanceHeadStandIn || $isAccountingStandIn) && !$isAdmin && !$approval) {
            $_SESSION['error'] = 'No pending approval step found at this stage.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }

        // FIX 4: A directly-assigned approver (e.g. an employee's supervisor_id points
        // to someone whose own account role differs from the pipeline step's role_id,
        // such as a Finance Head set as another employee's Immediate Head) is authorized
        // by virtue of owning the pending approval row itself — role match isn't required.
        $isDirectlyAssignedApprover = $approval
            && (int) $approval['approver_id'] === $userId
            && !($form['form_type'] === 'reimbursement'
                && in_array($roleId, [5, 9], true)
                && $roleId !== (int) $step['role_id']);
        $actorAllowed = $isAdmin || $isAdminApproverStandIn || $isMasterApproverStandIn || $isFinalApproverStandIn || $isHrVerifierStandIn || $isFinanceHeadStandIn || $isAccountingStandIn || $action === 'submit'
            || $isDirectlyAssignedApprover
            || $this->roleSatisfiesStage($roleId, $step['role_id'], $form['form_type']);
        if (!$actorAllowed) {
            $_SESSION['error'] = 'You are not authorized to perform this action.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }

        if ($action === 'submit' && !$isAdmin && (int)$form['submitted_by'] !== $userId) {
            $_SESSION['error'] = 'Only the form owner can submit this form.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }

        $isCheckVoucherStage = $action === 'process-approval'
            && in_array($form['form_type'], self::CHECK_VOUCHER_FORM_TYPES, true)
            && ($roleId === 1 || $roleId === 5);
        if ($isCheckVoucherStage && $checkVoucher === '') {
            $_SESSION['error'] = 'A Check Voucher is required for Accounting Checking.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }
        if ($isCheckVoucherStage && strlen($checkVoucher) > 100) {
            $_SESSION['error'] = 'The Check Voucher must be 100 characters or fewer.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }

        $isApprovedAmountStage = $action === 'process-approval'
            && $roleId === 5
            && in_array($form['form_type'], self::APPROVED_AMOUNT_FORM_TYPES, true);
        if ($isApprovedAmountStage && !preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $approvedAmount)) {
            $_SESSION['error'] = 'Enter a valid Approved Amount with up to 13 whole digits and 2 decimal places.';
            header("Location: " . url("forms/view/{$id}"));
            exit;
        }

        $uploadedFilePath = null;
        if (!empty($_FILES['approval_file']['tmp_name'])) {
            $file = $_FILES['approval_file'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $maxBytes = 20 * 1024 * 1024;
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            if (!in_array($mimeType, $allowed, true)) {
                $_SESSION['error'] = 'Only images and PDF files are allowed.';
                header("Location: " . url("forms/view/{$id}"));
                exit;
            }
            if ($file['size'] > $maxBytes) {
                $_SESSION['error'] = 'File must be under 20 MB.';
                header("Location: " . url("forms/view/{$id}"));
                exit;
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $destDir = __DIR__ . '/../../public/uploads/approvals/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            $fileName = sprintf('%d_%d_%s.%s', $id, time(), bin2hex(random_bytes(4)), $ext);
            if (!move_uploaded_file($file['tmp_name'], $destDir . $fileName)) {
                $_SESSION['error'] = 'File upload failed. Please try again.';
                header("Location: " . url("forms/view/{$id}"));
                exit;
            }
            $uploadedFilePath = 'uploads/approvals/' . $fileName;
        }

        // Pre-flight check: on Reimbursement/Liquidation forms, an HR Verifier
        // approving the Process stage triggers auto-creation of the Accounting
        // co-sign row (below). If no active Accounting approver exists, that
        // used to throw *inside* the transaction and roll back the HR
        // Verifier's own approval too — surfacing as a generic "Action failed"
        // with no indication of the real cause. Check this up front instead,
        // so the HR Verifier's approval is never silently lost and the error
        // actually points at the fix.
        if ($action === 'process-approval' && $form['form_type'] === 'liquidation' && $roleId === 9) {
            $accAlreadySeededCheck = db()->prepare(
                "SELECT COUNT(*) FROM approvals a
                JOIN employees e ON e.id = a.approver_id
                WHERE a.form_id = ? AND a.sequence = 3 AND e.role_id = 5"
            );
            $accAlreadySeededCheck->execute([$id]);
            if ((int) $accAlreadySeededCheck->fetchColumn() === 0) {
                $formDataCheck = json_decode($form['data'], true) ?? [];
                $accIdsCheck = $this->resolveApproversByRole(db(), 5, $formDataCheck, (int) $form['submitted_by'], $form['form_type']);
                if (empty($accIdsCheck)) {
                    $_SESSION['error'] = 'Your approval could not be recorded: there is no active Accounting (AcquisitionChecker) account configured to co-sign the Process stage. Ask a System Admin to add or reactivate one.';
                    header("Location: " . url("forms/view/{$id}"));
                    exit;
                }
            }
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            if ($approval) {
                // Record the person who actually clicked Approve. For a normal
                // approver this is a no-op (approver_id already equals $userId). 
                // For a stand-in (AdminApprover/MasterApprover/FinalApprover 
                // shared queue) this reassigns the row to them — this is both
                // the correct audit trail and what keeps findForm() letting
                // them view the form afterwards (it checks approver_id = them,
                // regardless of status). Admin (role 1) overrides are left
                // attributed to the originally assigned approver.
                $actingApproverId = $isAdmin ? (int) $approval['approver_id'] : $userId;

                $updated = $pdo->prepare(
                    "UPDATE approvals
                    SET status = 'approved', approver_id = ?, remarks = ?, file_path = ?, approved_at = NOW()
                    WHERE id = ? AND status = 'pending'"
                );
                $updated->execute([
                    $actingApproverId,
                    $remarks,
                    $uploadedFilePath,
                    $approval['id'],
                ]);

                if ($updated->rowCount() === 0) {
                    // Someone else (another stand-in approver) already actioned
                    // this exact row between our SELECT and this UPDATE.
                    $pdo->rollBack();
                    $_SESSION['error'] = 'This request was already actioned by another approver.';
                    header("Location: " . url("forms/view/{$id}"));
                    exit;
                }
            }

            if ($isCheckVoucherStage) {
                $pdo->prepare('UPDATE forms SET check_voucher = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$checkVoucher, $id]);
            }

            if ($isApprovedAmountStage) {
                $pdo->prepare('UPDATE forms SET approved_amount = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$approvedAmount, $id]);
            }

            $isFinanceProcessStage = $action === 'process-approval'
                && $form['form_type'] === 'liquidation'
                && in_array($roleId, [5, 9], true);

            if ($isFinanceProcessStage) {
                $accAlreadySeeded = $pdo->prepare(
                    "SELECT COUNT(*) FROM approvals a
                    JOIN employees e ON e.id = a.approver_id
                    WHERE a.form_id = ? AND a.sequence = 3 AND e.role_id = 5"
                );
                $accAlreadySeeded->execute([$id]);
                if ((int) $accAlreadySeeded->fetchColumn() === 0) {
                    $formData = json_decode($form['data'], true) ?? [];
                    $accIds = $this->resolveApproversByRole($pdo, 5, $formData, (int) $form['submitted_by'], $form['form_type']);
                    if (empty($accIds)) {
                        throw new \RuntimeException('No active Accounting approver found for co-sign.');
                    }
                    $accInsert = $pdo->prepare(
                        "INSERT INTO approvals (form_id, approver_id, sequence, status) VALUES (?, ?, 3, 'pending')"
                    );
                    foreach ($accIds as $accId) {
                        $accInsert->execute([$id, $accId]);
                    }
                }
            }

            $remaining = $pdo->prepare(
                "SELECT COUNT(*) FROM approvals WHERE form_id = ? AND sequence = ? AND status = 'pending'"
            );
            $remaining->execute([$id, $step['sequence']]);
            $stillPending = (int) $remaining->fetchColumn();
            $advanced = $isAdmin || $stillPending === 0;
            $nextStatus = $step['to'];

            if ($advanced) {
                if ((int) $step['sequence'] === 2 && isset($pipeline['process-approval'])) {
                    $pendingAccounting = $pdo->prepare(
                        "SELECT COUNT(*) FROM approvals WHERE form_id = ? AND sequence = 3 AND status = 'pending'"
                    );
                    $pendingAccounting->execute([$id]);
                    $approvedAccounting = $pdo->prepare(
                        "SELECT COUNT(*) FROM approvals WHERE form_id = ? AND sequence = 3 AND status = 'approved'"
                    );
                    $approvedAccounting->execute([$id]);
                    if ((int) $pendingAccounting->fetchColumn() === 0
                        && (int) $approvedAccounting->fetchColumn() > 0
                    ) {
                        $nextStatus = $pipeline['process-approval']['to'];
                    }
                }
                if ($form['form_type'] === 'reimbursement' && (int) $step['sequence'] === 3) {
                    $pendingAccounting = $pdo->prepare(
                        "SELECT COUNT(*) FROM approvals WHERE form_id = ? AND sequence = 4 AND status = 'pending'"
                    );
                    $pendingAccounting->execute([$id]);
                    $approvedAccounting = $pdo->prepare(
                        "SELECT COUNT(*) FROM approvals WHERE form_id = ? AND sequence = 4 AND status = 'approved'"
                    );
                    $approvedAccounting->execute([$id]);
                    if ((int) $pendingAccounting->fetchColumn() === 0
                        && (int) $approvedAccounting->fetchColumn() > 0
                    ) {
                        $nextStatus = $pipeline['process-approval']['to'];
                    }
                }
                $pdo->prepare("UPDATE forms SET status = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$nextStatus, $id]);
            }

            $this->audit('form_approved', 'form', $id,
                ['status' => $form['status']],
                ['status' => $advanced ? $nextStatus : $form['status'], 'action' => $action]
            );

            $pdo->commit();
            \App\Services\RealtimeEventService::publishPendingCountChanged();

            $_SESSION['success'] = $advanced
                ? "Form {$step['label']} approved successfully."
                : 'Your approval has been recorded. Waiting for co-approver.';

            if ($advanced) {
                $this->sendPipelineNotifications($id, $action, $step, $remarks);
            }

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log(sprintf(
                '[FormController] Approval action failed for form %d (%s): %s',
                $id,
                $action,
                $e->getMessage()
            ));
            $_SESSION['error'] = 'Action failed. Please try again.';
        }

        header("Location: " . url("forms/view/{$id}"));
        exit;
    }

    // FIX 4: Removed incorrectly placed HR seed block that referenced $action/$form/$id
    private function store(string $type, string $slug): void {
        \App\Helpers\Csrf::verify();

        $uploadedPaths = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
            $maxBytes = 20 * 1024 * 1024;
            $destDir = __DIR__ . '/../../public/uploads/forms/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            $finfo = new \finfo(FILEINFO_MIME_TYPE);

            foreach ($_FILES['attachments']['tmp_name'] as $i => $tmp) {
                if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $mime = $finfo->file($tmp);
                if (!in_array($mime, $allowed, true) || $_FILES['attachments']['size'][$i] > $maxBytes) {
                    $_SESSION['error'] = 'Invalid file or file exceeds 20 MB.';
                    header("Location: " . url("forms/{$slug}/create"));
                    exit;
                }
                $ext = pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION);
                $fileName = sprintf('%s_%s.%s', time(), bin2hex(random_bytes(4)), $ext);
                move_uploaded_file($tmp, $destDir . $fileName);
                $uploadedPaths[] = 'uploads/forms/' . $fileName;
            }
        }

        $isSavingDraft = isset($_POST['save_draft']);
        $required = $this->fields[$type];
        $data = [];

        if (!$isSavingDraft) {
            foreach ($required as $field) {
                $val = $_POST[$field] ?? '';
                if (is_string($val)) $val = trim($val);
                if ($val === '' || (is_array($val) && empty(array_filter($val)))) {
                    $_SESSION['error'] = "Field '{$field}' is required.";
                    header("Location: " . url("forms/{$slug}/create"));
                    exit;
                }
            }
        }

        foreach ($_POST as $key => $val) {
            if (in_array($key, ['csrf_token', 'save_draft'], true)) continue;
            if (is_array($val)) {
                $data[$key] = array_map(fn($v) => htmlspecialchars(trim($v), ENT_QUOTES), $val);
            } else {
                $data[$key] = htmlspecialchars(trim($val), ENT_QUOTES);
            }
        }

        if (!empty($uploadedPaths)) {
            $data['attachments'] = $uploadedPaths;
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $initialStatus = $isSavingDraft ? 'draft' : 'submitted';

            $stmt = $pdo->prepare(
                "INSERT INTO forms (form_type, status, submitted_by, data) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$type, $initialStatus, $_SESSION['user_id'], json_encode($data)]);
            $formId = (int) $pdo->lastInsertId();

            $submitterId = (int) $_SESSION['user_id'];
            $submitterDepartment = $this->getEmployeeDepartment($pdo, $submitterId);
            $this->seedApprovalRows($pdo, $formId, $type, $data, $submitterId, $submitterDepartment);

            if (!$isSavingDraft) {
                $this->initializeSubmittedApprovals($pdo, $formId, $type, $submitterDepartment);
            }

            $this->audit('form_created', 'form', $formId, null, ['type' => $type, 'status' => $initialStatus]);

            $pdo->commit();
            if (!$isSavingDraft) {
                \App\Services\RealtimeEventService::publishPendingCountChanged();
            }

            $_SESSION['success'] = $isSavingDraft
                ? 'This form is already saved in Drafts.'
                : 'Form submitted successfully and is now pending approval.';

            header('Location: ' . ($isSavingDraft ? url('dashboard') : url('forms/view/' . $formId)));
            exit;

        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Submission failed: ' . $e->getMessage();
            header("Location: " . url("forms/{$slug}/create"));
            exit;
        }
    }

    private function seedApprovalRows(
        \PDO $pdo,
        int $formId,
        string $type,
        array $data,
        int $submitterId,
        ?string $submitterDepartment = null
    ): void {
        $pipeline = $this->getPipeline($type, $submitterDepartment, $submitterId);
        $isFinalHrFlow = $this->isFinalHrDepartmentRequest($type, $submitterDepartment);
        $isHrFirstCoSign = $type === 'liquidation';
        $submitterRoleId = (int) ($_SESSION['role_id'] ?? 0);
        $submitterId = (int) ($_SESSION['user_id'] ?? 0);
        $hasAssignedImmediateHead = $this->employeeHasAssignedImmediateHead($pdo, $submitterId);
        $isSupervisorSubmitter = $submitterRoleId === 2;
        $isHrSubmitter = $submitterRoleId === self::HR_VERIFIER_ROLE;
        $isAdminApproverSubmitter = $submitterRoleId === self::ADMIN_APPROVER_ROLE;

        $insert = $pdo->prepare(
            "INSERT INTO approvals (form_id, approver_id, sequence, status) VALUES (?, ?, ?, 'pending')"
        );
        $insert->execute([$formId, $submitterId, 1]);

        $stagesNeedingApprover = array_filter($pipeline, fn($step) => $step['sequence'] >= 2);
        foreach ($stagesNeedingApprover as $step) {
            if ($type === 'reimbursement' && $submitterRoleId === 5 && $step['sequence'] === 4) {
                $pdo->prepare(
                    "INSERT INTO approvals (form_id, approver_id, sequence, status, approved_at, remarks)
                     VALUES (?, ?, 4, 'approved', NOW(), 'Skipped: requestor is the AcquisitionChecker')"
                )->execute([$formId, $submitterId]);
                continue;
            }

            // Sequence 2 is self-approved for Supervisor, HR Verifier, and Admin Approver requestors.
            $shouldSkipSupervisorVerification = !$isFinalHrFlow
                && ($isSupervisorSubmitter || $isHrSubmitter || $isAdminApproverSubmitter)
                && $step['sequence'] === 2
                && !(
                    in_array($type, ['advance_payment', 'request_for_payment', 'reimbursement'], true)
                    && $isSupervisorSubmitter
                    && $hasAssignedImmediateHead
                );
            if ($shouldSkipSupervisorVerification) {
                $insert->execute([$formId, $submitterId, 2]);
                continue;
            }
            
            if ($isHrFirstCoSign && $step['sequence'] === 3) {
                $coSignersByRole = [
                    9 => $this->resolveApproversByRole($pdo, 9, $data, $submitterId, $type),
                    5 => $this->resolveApproversByRole($pdo, 5, $data, $submitterId, $type),
                ];
                foreach ($coSignersByRole as $coSignerRoleId => $approverIds) {
                    if (empty($approverIds)) {
                        $roleLabel = $coSignerRoleId === 9 ? 'HR Verifier' : 'Accounting';
                        throw new \RuntimeException("No active {$roleLabel} found for Process Approval stage.");
                    }
                    foreach ($approverIds as $approverId) {
                        $insert->execute([$formId, $approverId, 3]);
                    }
                }
                continue;
            }

            if ($type === 'reimbursement'
                && $submitterRoleId === self::HR_VERIFIER_ROLE
                && $step['sequence'] === 3
                && (int) $step['role_id'] === self::HR_VERIFIER_ROLE
            ) {
                $otherHrVerifier = $pdo->prepare(
                    "SELECT e.id FROM employees e
                     LEFT JOIN approvals a ON a.approver_id = e.id AND a.status = 'pending'
                     WHERE e.role_id = ? AND e.is_active = 1 AND e.id <> ?
                     GROUP BY e.id ORDER BY COUNT(a.id) ASC, e.id ASC LIMIT 1"
                );
                $otherHrVerifier->execute([self::HR_VERIFIER_ROLE, $submitterId]);
                $otherHrVerifierId = $otherHrVerifier->fetchColumn();
                $approvers = $otherHrVerifierId === false ? [] : [(int) $otherHrVerifierId];
            } elseif ($type === 'vehicle_request' && $step['sequence'] === 3) {
                $approvers = $this->resolveVehicleAdminEvaluator($pdo);
            } else {
                $approvers = $this->resolveApproversByRole($pdo, $step['role_id'], $data, $submitterId, $type);
            }
            if (empty($approvers)) {
                if ($step['sequence'] === 2) {
                    continue;
                }
                throw new \RuntimeException("No active approver found for stage '{$step['label']}'.");
            }
            foreach ($approvers as $approverId) {
                $insert->execute([$formId, $approverId, $step['sequence']]);
            }
        }
    }

    private function resolveVehicleAdminEvaluator(\PDO $pdo): array {
        $stmt = $pdo->query(
            "SELECT e.id
             FROM employees e
             LEFT JOIN approvals a ON a.approver_id = e.id AND a.status = 'pending'
             WHERE e.role_id = 2
               AND e.is_active = 1
               AND LOWER(e.department) REGEXP '(^|[^[:alnum:]])admin(istration)?([^[:alnum:]]|$)'
             GROUP BY e.id
             ORDER BY COUNT(a.id) ASC, e.id ASC
             LIMIT 1"
        );
        $adminHeadId = $stmt->fetchColumn();

        return $adminHeadId === false ? [] : [(int) $adminHeadId];
    }

    private function initializeSubmittedApprovals(
        \PDO $pdo,
        int $formId,
        string $type,
        ?string $submitterDepartment = null
    ): void {
        $isFinalHrFlow = $this->isFinalHrDepartmentRequest($type, $submitterDepartment);
        $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
        $submitterRoleId = (int) ($_SESSION['role_id'] ?? 0);
        $hasAssignedImmediateHead = $this->employeeHasAssignedImmediateHead($pdo, $currentUserId);
        $skipSupervisorVerificationForSupervisorSubmitter = in_array($type, ['advance_payment', 'request_for_payment', 'reimbursement'], true)
            && $submitterRoleId === 2
            && $hasAssignedImmediateHead;

        $pdo->prepare(
            "UPDATE approvals SET status = 'approved', approved_at = NOW(), remarks = ''
             WHERE form_id = ? AND sequence = 1"
        )->execute([$formId]);

        if ($type === 'reimbursement' && $submitterRoleId === 5) {
            $pdo->prepare(
                "UPDATE approvals SET status = 'approved', approver_id = ?, approved_at = NOW(),
                 remarks = 'Skipped: requestor is the AcquisitionChecker'
                 WHERE form_id = ? AND sequence = 2 AND status = 'pending'"
            )->execute([$currentUserId, $formId]);
            $pdo->prepare(
                "UPDATE forms SET status = 'immediatehead_approved', updated_at = NOW() WHERE id = ?"
            )->execute([$formId]);
        }

        if (!$isFinalHrFlow && in_array($submitterRoleId, [2, self::HR_VERIFIER_ROLE], true)
            && !$skipSupervisorVerificationForSupervisorSubmitter) {
            $pdo->prepare(
                "UPDATE approvals SET status = 'approved', approver_id = ?, approved_at = NOW(),
                 remarks = 'Auto-approved: submitter is the Immediate Head or HR Verifier'
                 WHERE form_id = ? AND sequence = 2"
            )->execute([$currentUserId, $formId]);

            $pdo->prepare(
                "UPDATE forms SET status = 'immediatehead_approved', updated_at = NOW() WHERE id = ?"
            )->execute([$formId]);
        }

        if (!$isFinalHrFlow && (int) $_SESSION['role_id'] === self::ADMIN_APPROVER_ROLE) {
            $pdo->prepare(
                "UPDATE approvals SET status = 'approved', approver_id = ?, approved_at = NOW(),
                 remarks = 'Auto-approved: submitted by Admin Approver'
                 WHERE form_id = ? AND sequence = 2"
            )->execute([(int) $_SESSION['user_id'], $formId]);

            $pdo->prepare(
                "UPDATE forms SET status = 'immediatehead_approved', updated_at = NOW() WHERE id = ?"
            )->execute([$formId]);
        }

        if (!$isFinalHrFlow
            && !$skipSupervisorVerificationForSupervisorSubmitter
            && ($type !== 'vehicle_request' || $this->hasVehicleSupervisorApproval($formId))
        ) {
            $selfSupervisor = $pdo->prepare(
                "UPDATE approvals SET status = 'approved', approved_at = NOW(),
                        remarks = 'Auto-approved: submitter is the assigned supervisor'
                 WHERE form_id = ? AND sequence = 2 AND approver_id = ? AND status = 'pending'"
            );
            $selfSupervisor->execute([$formId, $currentUserId]);

            $pendingSupervisorApproval = $pdo->prepare(
                "SELECT COUNT(*) FROM approvals
                 WHERE form_id = ? AND sequence = 2 AND status = 'pending'"
            );
            $pendingSupervisorApproval->execute([$formId]);
            if ((int) $pendingSupervisorApproval->fetchColumn() === 0) {
                $pdo->prepare(
                    "UPDATE forms SET status = 'immediatehead_approved', updated_at = NOW() WHERE id = ?"
                )->execute([$formId]);
            }
        }

        if ((int) $_SESSION['role_id'] === 5) {
            $accountingSequence = $type === 'reimbursement' ? 4 : 3;
            $selfAccounting = $pdo->prepare(
                "UPDATE approvals a
                 JOIN employees e ON e.id = a.approver_id
                 SET a.status = 'approved', a.approved_at = NOW(),
                     a.remarks = 'Auto-approved: submitted by Accounting'
                 WHERE a.form_id = ? AND a.sequence = ? AND a.status = 'pending'
                   AND e.role_id = 5"
            );
            $selfAccounting->execute([$formId, $accountingSequence]);
            if ($selfAccounting->rowCount() > 0) {
                $pendingEarlierSteps = $pdo->prepare(
                    "SELECT COUNT(*) FROM approvals
                     WHERE form_id = ? AND sequence IN (2, 3, 4) AND status = 'pending'"
                );
                $pendingEarlierSteps->execute([$formId]);
                if ((int) $pendingEarlierSteps->fetchColumn() === 0) {
                    $pdo->prepare(
                        "UPDATE forms SET status = 'process_approved', updated_at = NOW() WHERE id = ?"
                    )->execute([$formId]);
                }
            }
        }
    }

    private function resolveApproversByRole(\PDO $pdo, int $roleId, array $data, int $submitterId, string $formType = ''): array {
        if ($roleId === 2) {
            $stmt = $pdo->prepare(
                'SELECT supervisor_id, supervisor_id_2 FROM employees WHERE id = ? AND is_active = 1'
            );
            $stmt->execute([$submitterId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return [];

            $isDualSignoffForm = $formType === 'vehicle_request';
            $supervisorIds = array_unique(array_filter([
                $row['supervisor_id'] ? (int) $row['supervisor_id'] : null,
                ($isDualSignoffForm && $row['supervisor_id_2']) ? (int) $row['supervisor_id_2'] : null,
            ]));
            if (empty($supervisorIds)) return [];

            $placeholders = implode(',', array_fill(0, count($supervisorIds), '?'));
            $check = $pdo->prepare("SELECT id FROM employees WHERE id IN ($placeholders) AND is_active = 1");
            $check->execute(array_values($supervisorIds));
            return array_map('intval', $check->fetchAll(\PDO::FETCH_COLUMN));
        }

        if ($roleId === 4) {
            $stmt = $pdo->prepare(
                'SELECT master_approver_id FROM employees WHERE id = ? AND is_active = 1'
            );
            $stmt->execute([$submitterId]);
            $assignedId = $stmt->fetchColumn();

            if ($assignedId) {
                $check = $pdo->prepare(
                    'SELECT id FROM employees WHERE id = ? AND role_id IN (4, 7) AND is_active = 1'
                );
                $check->execute([(int) $assignedId]);
                if ($check->fetchColumn()) return [(int) $assignedId];
            }
        }

        if ($roleId === 6) {
            $row = $pdo->query(
                "SELECT e.id FROM employees e
                LEFT JOIN approvals a ON a.approver_id = e.id AND a.status = 'pending'
                WHERE e.role_id = 6 AND e.is_active = 1
                GROUP BY e.id ORDER BY COUNT(a.id) ASC, e.id ASC LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            if ($row) return [(int) $row['id']];

            $fallback = $pdo->query(
                "SELECT e.id FROM employees e
                LEFT JOIN approvals a ON a.approver_id = e.id AND a.status = 'pending'
                WHERE e.role_id = 7 AND e.is_active = 1
                GROUP BY e.id ORDER BY COUNT(a.id) ASC, e.id ASC LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            return $fallback ? [(int) $fallback['id']] : [];
        }

        if ($roleId === self::FINANCE_HEAD_ROLE) {
            $stmt = $pdo->prepare(
                'SELECT finance_head_id FROM employees WHERE id = ? AND is_active = 1'
            );
            $stmt->execute([$submitterId]);
            $assignedId = $stmt->fetchColumn();

            if ($assignedId) {
                $check = $pdo->prepare(
                    'SELECT id FROM employees WHERE id = ? AND role_id = ? AND is_active = 1'
                );
                $check->execute([(int) $assignedId, self::FINANCE_HEAD_ROLE]);
                if ($check->fetchColumn()) return [(int) $assignedId];
            }
        }

        if ($roleId === self::HR_VERIFIER_ROLE) {
            $stmt = $pdo->prepare(
                'SELECT hr_verifier_id FROM employees WHERE id = ? AND is_active = 1'
            );
            $stmt->execute([$submitterId]);
            $assignedId = $stmt->fetchColumn();

            if ($assignedId) {
                $check = $pdo->prepare(
                    'SELECT id FROM employees WHERE id = ? AND role_id = ? AND is_active = 1'
                );
                $check->execute([(int) $assignedId, self::HR_VERIFIER_ROLE]);
                if ($check->fetchColumn()) return [(int) $assignedId];
            }

            $row = $pdo->query(
                "SELECT e.id FROM employees e
                LEFT JOIN approvals a ON a.approver_id = e.id AND a.status = 'pending'
                WHERE e.role_id = 9 AND e.is_active = 1
                GROUP BY e.id ORDER BY COUNT(a.id) ASC, e.id ASC LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            return $row ? [(int) $row['id']] : [];
        }

        // Generic workload-balanced fallback (roles 3, 5, and any unhandled role)
        $dept = $data['department'] ?? null;
        $sql = "SELECT e.id FROM employees e
                LEFT JOIN approvals a ON a.approver_id = e.id AND a.status = 'pending'
                WHERE e.role_id = ? AND e.is_active = 1";
        $params = [$roleId];

        if ($dept && !in_array($roleId, [1, 4, 5, 6, 7, 8, 9], true)) {
            $sql .= ' AND e.department = ?';
            $params[] = $dept;
        }
        $sql .= ' GROUP BY e.id ORDER BY COUNT(a.id) ASC, e.id ASC LIMIT 1';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) return [(int) $row['id']];

        if ($roleId === 4) {
            $fallback = $pdo->prepare(
                "SELECT e.id FROM employees e
                LEFT JOIN approvals a ON a.approver_id = e.id AND a.status = 'pending'
                WHERE e.role_id = 7 AND e.is_active = 1
                GROUP BY e.id ORDER BY COUNT(a.id) ASC, e.id ASC LIMIT 1"
            );
            $fallback->execute();
            $fallbackRow = $fallback->fetch(\PDO::FETCH_ASSOC);
            if ($fallbackRow) return [(int) $fallbackRow['id']];
        }

        return [];
    }

    private function canActOnForm(array $form, array $steps): bool {
        if (in_array($form['status'], ['completed', 'rejected'], true)) return false;

        $userId = (int) $_SESSION['user_id'];
        $roleId = (int) $_SESSION['role_id'];
        if ($form['form_type'] === 'reimbursement'
            && $roleId === self::HR_VERIFIER_ROLE
            && (int) $form['submitted_by'] === $userId
        ) return false;
        if ($roleId === 1) return true;

        if ($form['status'] === 'draft' && (int)$form['submitted_by'] === $userId) return true;

        foreach ($steps as $step) {
            if ((int)$step['approver_id'] !== $userId || $step['status'] !== 'pending') continue;
            $mySeq   = (int)$step['sequence'];
            if ($form['form_type'] === 'reimbursement' && in_array($roleId, [5, 9], true)) {
                $pipeline = $this->getPipeline($form['form_type'], $form['submitter_department'] ?? null, (int) $form['submitted_by'], (int) $form['id']);
                $expectedRole = null;
                foreach ($pipeline as $pipelineStep) {
                    if ((int) $pipelineStep['sequence'] === $mySeq) {
                        $expectedRole = (int) $pipelineStep['role_id'];
                        break;
                    }
                }
                if ($roleId !== $expectedRole) continue;
            }
            $blocked = false;
            foreach ($steps as $other) {
                if ((int)$other['approver_id'] !== $userId
                    && $other['status'] === 'pending'
                    && (int)$other['sequence'] < $mySeq
                ) { $blocked = true; break; }
            }
            if (!$blocked) return true;
        }

        // AdminApprover (role 7) stand-in
        if ($roleId === 7) {
            foreach ($steps as $step) {
                if ($step['status'] !== 'pending') continue;
                $mySeq = (int)$step['sequence'];
                $blocked = false;
                foreach ($steps as $other) {
                    if ($other['status'] === 'pending' && (int)$other['sequence'] < $mySeq) {
                        $blocked = true; break;
                    }
                }
                if (!$blocked) {
                    $pipeline = $this->getPipeline($form['form_type'], $form['submitter_department'] ?? null, (int) $form['submitted_by'], (int) $form['id']);
                    foreach ($pipeline as $pStep) {
                        if ((int)$pStep['sequence'] === $mySeq
                            && $this->adminApproverStandsInFor($form['form_type'], (int)$pStep['role_id'])
                        ) return true;
                    }
                }
            }
        }

        // MasterApprover (role 4) stand-in for Checker (Immediate Head) stage
        // on admin-category forms (Overtime, Leave, Vehicle Request)
        if ($roleId === 4) {
            foreach ($steps as $step) {
                if ($step['status'] !== 'pending') continue;
                $mySeq   = (int)$step['sequence'];
                $blocked = false;
                foreach ($steps as $other) {
                    if ($other['status'] === 'pending' && (int)$other['sequence'] < $mySeq) {
                        $blocked = true; break;
                    }
                }
                if (!$blocked) {
                    $pipeline = $this->getPipeline($form['form_type'], $form['submitter_department'] ?? null, (int) $form['submitted_by'], (int) $form['id']);
                    foreach ($pipeline as $pStep) {
                        if ((int)$pStep['sequence'] === $mySeq
                            && $this->masterApproverStandsInFor($form['form_type'], (int)$pStep['role_id'])
                        ) return true;
                    }
                }
            }
        }

        // FinalApprover (role 6) shared queue: any Final Approver may act on any
        // pending Grant Approval row, regardless of who it was auto-assigned to.
        if ($roleId === 6) {
            foreach ($steps as $step) {
                if ($step['status'] !== 'pending') continue;
                if ((int)$step['approver_role_id'] !== 6) continue;
                $mySeq = (int)$step['sequence'];
                $blocked = false;
                foreach ($steps as $other) {
                    if ($other['status'] === 'pending' && (int)$other['sequence'] < $mySeq) {
                        $blocked = true; break;
                    }
                }
                if (!$blocked) return true;
            }
        }

        // HRVerifier (role 9) shared queue: any HR Verifier may act on any
        // pending Process Approval (HR Verification) row, regardless of who
        // it was auto-assigned to.
        if ($roleId === 9) {
            foreach ($steps as $step) {
                if ($step['status'] !== 'pending') continue;
                if ((int)$step['approver_role_id'] !== 9) continue;
                $mySeq = (int)$step['sequence'];
                if ($form['form_type'] === 'reimbursement' && $mySeq !== 3) continue;
                $blocked = false;
                foreach ($steps as $other) {
                    if ($other['status'] === 'pending' && (int)$other['sequence'] < $mySeq) {
                        $blocked = true; break;
                    }
                }
                if (!$blocked) return true;
            }
        }

        // FinanceHead (role 8) shared queue: any Finance Head may act on any
        // pending Evaluation Approval row, regardless of who it was
        // auto-assigned to.
        if ($roleId === 8) {
            foreach ($steps as $step) {
                if ($step['status'] !== 'pending') continue;
                if ((int)$step['approver_role_id'] !== 8) continue;
                $mySeq = (int)$step['sequence'];
                $blocked = false;
                foreach ($steps as $other) {
                    if ($other['status'] === 'pending' && (int)$other['sequence'] < $mySeq) {
                        $blocked = true; break;
                    }
                }
                if (!$blocked) return true;
            }
        }

        // AcquisitionChecker/Accounting (role 5) shared queue: any Accounting
        // account may act on any pending Process (Accounting Checking) row,
        // regardless of who it was auto-assigned to.
        if ($roleId === 5) {
            foreach ($steps as $step) {
                if ($step['status'] !== 'pending') continue;
                if ((int)$step['approver_role_id'] !== 5) continue;
                $mySeq = (int)$step['sequence'];
                if ($form['form_type'] === 'reimbursement' && $mySeq !== 4) continue;
                $blocked = false;
                foreach ($steps as $other) {
                    if ($other['status'] === 'pending' && (int)$other['sequence'] < $mySeq) {
                        $blocked = true; break;
                    }
                }
                if (!$blocked) return true;
            }

            if ($form['form_type'] === 'liquidation') {
                foreach ($steps as $step) {
                    if ($step['status'] !== 'pending' || (int)$step['approver_role_id'] !== 9) continue;
                    $mySeq = (int)$step['sequence'];
                    $blocked = false;
                    foreach ($steps as $other) {
                        if ($other['status'] === 'pending' && (int)$other['sequence'] < $mySeq) {
                            $blocked = true;
                            break;
                        }
                    }
                    if (!$blocked) return true;
                }
            }
        }

        return false;
    }

    private function findForm(int $id): array {
        $stmt = db()->prepare(
            'SELECT f.id, f.form_type, f.status, f.data, f.submitted_by, f.created_at,
                    f.updated_at, f.approved_amount, e.full_name AS submitter_name,
                    e.role_id AS submitter_role_id, e.department AS submitter_department
             FROM forms f JOIN employees e ON e.id = f.submitted_by
             WHERE f.id = ?'
        );
        $stmt->execute([$id]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$form) return $this->renderError(404, 'Not Found', 'The form you are looking for does not exist.');

        $roleId = (int) $_SESSION['role_id'];
        $userId = (int) $_SESSION['user_id'];
        $viewerRoleStmt = db()->prepare(
            'SELECT e.role_id, r.name
             FROM employees e
             JOIN roles r ON r.id = e.role_id
             WHERE e.id = ?'
        );
        $viewerRoleStmt->execute([$userId]);
        $viewerRole = $viewerRoleStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $viewerRoleId = (int) ($viewerRole['role_id'] ?? 0);
        $normalizedViewerRoleName = strtolower(preg_replace(
            '/[^a-z]/i',
            '',
            (string) ($viewerRole['name'] ?? '')
        ));

        if (
            ($roleId === self::HR_VERIFIER_ROLE
                || $viewerRoleId === self::HR_VERIFIER_ROLE
                || $normalizedViewerRoleName === 'hrverifier')
            && in_array($form['form_type'], ['advance_payment', 'request_for_payment'], true)
        ) {
            return $this->renderError(403, 'Access Denied', 'HR Verifiers do not have access to this form type.');
        }

        if (in_array($roleId, [6, 7, self::HR_VERIFIER_ROLE], true)
            || in_array($viewerRoleId, [6, 7, self::HR_VERIFIER_ROLE], true)
            || in_array($normalizedViewerRoleName, ['adminapprover', 'finalapprover', 'hrverifier'], true)
        ) {
            return $form;
        }

        if (
            $this->isFinalHrDepartmentRequest((string) $form['form_type'], $form['submitter_department'] ?? null)
            && (int) $form['submitted_by'] !== $userId
            && !in_array($roleId, [1, 6, 7, 9], true)
        ) {
            return $this->renderError(403, 'Access Denied', 'You do not have permission to view this form.');
        }

        if ($roleId === 1 || $roleId !== 3) return $form;
        if ((int)$form['submitted_by'] === $userId) return $form;

        return $this->renderError(403, 'Access Denied', 'You do not have permission to view this form.');
    }

    private function sendPipelineNotifications(int $formId, string $action, array $step, string $remarks): void {
        try {
            $pdo = db();
            $formRow = $pdo->prepare(
                'SELECT f.form_type, f.status, f.submitted_by,
                        e.full_name AS submitter_name, e.email AS submitter_email
                 FROM forms f JOIN employees e ON e.id = f.submitted_by WHERE f.id = ?'
            );
            $formRow->execute([$formId]);
            $form = $formRow->fetch(\PDO::FETCH_ASSOC);
            if (!$form) return;

            $submittedBy = (int) $form['submitted_by'];
            $formLabel = \App\Helpers\FormLabels::get($form['form_type']);
            $stageName = $step['label'];
            $newStatus = $step['to'];

            $outcome = match($newStatus) {
                'completed' => 'completed',
                'final_approved' => 'final_approved',
                'rejected' => 'rejected',
                default => 'approved_step',
            };

            $submitterMsg = match($outcome) {
                'completed' => "Your {$formLabel} #{$formId} has been fully completed.",
                'final_approved' => "Your {$formLabel} #{$formId} reached final approval.",
                'rejected' => "Your {$formLabel} #{$formId} was rejected at {$stageName}.",
                default => "Your {$formLabel} #{$formId} passed {$stageName}.",
            };
            $submitterType = match($outcome) {
                'completed', 'final_approved' => 'success',
                'rejected' => 'danger',
                default => 'info',
            };

            \App\Controllers\NotificationController::create($submittedBy, $submitterMsg, $submitterType, $formId);
            \App\Services\NotificationService::notifySubmitter(
                $form['submitter_email'], $form['submitter_name'],
                $formLabel, $formId, $outcome, $stageName, $remarks
            );

            if (!in_array($newStatus, ['completed', 'rejected'], true)) {
                $nextRow = $pdo->prepare(
                    'SELECT a.approver_id, e.email, e.full_name, a.sequence
                     FROM approvals a JOIN employees e ON e.id = a.approver_id
                     WHERE a.form_id = ? AND a.status = \'pending\'
                     ORDER BY a.sequence ASC LIMIT 1'
                );
                $nextRow->execute([$formId]);
                $nextApprover = $nextRow->fetch(\PDO::FETCH_ASSOC);
                if ($nextApprover) {
                    $nextStageName = \App\Helpers\FormLabels::stepLabel((int)$nextApprover['sequence']);
                    \App\Controllers\NotificationController::create(
                        (int) $nextApprover['approver_id'],
                        "{$formLabel} #{$formId} from {$form['submitter_name']} requires your approval at {$nextStageName}.",
                        'warning', $formId
                    );
                    \App\Services\NotificationService::notifyNextApprover(
                        $formId, $nextApprover['email'], $nextApprover['full_name'],
                        $formLabel, $form['submitter_name'], $nextStageName
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log('[FormController] Notification error: ' . $e->getMessage());
        }
    }

    private function sendRejectionNotification(int $formId, string $remarks): void {
        try {
            $pdo = db();
            $row = $pdo->prepare(
                'SELECT f.form_type, f.submitted_by,
                        e.full_name AS submitter_name, e.email AS submitter_email
                 FROM forms f JOIN employees e ON e.id = f.submitted_by WHERE f.id = ?'
            );
            $row->execute([$formId]);
            $form = $row->fetch(\PDO::FETCH_ASSOC);
            if (!$form) return;

            $stageRow = $pdo->prepare(
                'SELECT sequence FROM approvals WHERE form_id = ? AND status = \'rejected\'
                 ORDER BY sequence DESC LIMIT 1'
            );
            $stageRow->execute([$formId]);
            $stageData = $stageRow->fetch(\PDO::FETCH_ASSOC);
            $stageName = \App\Helpers\FormLabels::stepLabel((int)($stageData['sequence'] ?? 0));
            $typeLabel = \App\Helpers\FormLabels::get($form['form_type']);

            $rejectMsg = "Your {$typeLabel} #{$formId} was rejected at {$stageName}"
                       . ($remarks ? ": \"{$remarks}\"" : '.');
            \App\Controllers\NotificationController::create((int) $form['submitted_by'], $rejectMsg, 'danger', $formId);
            \App\Services\NotificationService::notifySubmitter(
                $form['submitter_email'], $form['submitter_name'],
                $typeLabel, $formId, 'rejected', $stageName, $remarks
            );
        } catch (\Throwable $e) {
            error_log('[FormController] Rejection notification error: ' . $e->getMessage());
        }
    }

    public function uploadPaymentProof(int $id): void {
        \App\Helpers\Csrf::verify();

        if ((int)($_SESSION['role_id'] ?? 0) !== 5) {
            $_SESSION['error'] = 'Only the AcquisitionChecker can upload payment proof.';
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }

        $form = $this->findForm($id);
        if (in_array($form['form_type'], ['leave_application', 'overtime_authorization'], true)) {
            $_SESSION['error'] = 'Disbursement confirmation is not required for this request.';
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }
        if (($form['status'] ?? '') !== 'completed') {
            $_SESSION['error'] = 'Payment proof can only be uploaded after Final Authorization is complete.';
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }

        try {
            $attachments = $this->saveProofAttachments(
                $_FILES['payment_proof'] ?? [],
                'uploads/payment-proof/',
                $id,
                'payment proof'
            );
        } catch (\RuntimeException $exception) {
            $_SESSION['error'] = $exception->getMessage();
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }

        $data = json_decode($form['data'], true) ?? [];
        $data['payment_proof_attachments'] = $attachments;
        $data['payment_proof_path'] = $attachments[0]['path'];
        $data['payment_proof_uploaded_by'] = $_SESSION['user_name'] ?? 'AcquisitionChecker';
        $data['payment_proof_uploaded_at'] = date('Y-m-d H:i:s');
        $pdo = db();
        $pdo->prepare('UPDATE forms SET data = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($data), $id]);

        \App\Services\RealtimeEventService::publishPendingCountChanged();
        $_SESSION['success'] = 'Disbursement confirmation attachments uploaded successfully.';
        header('Location: ' . url('forms/view/' . $id));
        exit;
    }

    public function uploadLiquidationProof(int $id): void {
        \App\Helpers\Csrf::verify();

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $roleId = (int) ($_SESSION['role_id'] ?? 0);
        $form = $this->findForm($id);
        if (in_array($form['form_type'], ['leave_application', 'overtime_authorization'], true)) {
            $_SESSION['error'] = 'Expense liquidation is not required for this request.';
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }
        if ($form['form_type'] === 'reimbursement') {
            $_SESSION['error'] = 'Expense liquidation is not required for reimbursement requests.';
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }

        if (!$form || ($roleId !== 1 && (int) ($form['submitted_by'] ?? 0) !== $userId)) {
            $_SESSION['error'] = 'Only the requestor can upload the liquidation document.';
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }

        if (($form['status'] ?? '') !== 'completed') {
            $_SESSION['error'] = 'The liquidation document can only be uploaded after Final Authorization.';
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }

        $data = json_decode((string) ($form['data'] ?? '[]'), true);
        $data = is_array($data) ? $data : [];
        if (empty($data['payment_proof_path'])) {
            $_SESSION['error'] = 'The disbursement confirmation must be uploaded before expense liquidation.';
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }

        try {
            $attachments = $this->saveProofAttachments(
                $_FILES['liquidation_proof'] ?? [],
                'uploads/liquidation-proof/',
                $id,
                'liquidation proof'
            );
        } catch (\RuntimeException $exception) {
            $_SESSION['error'] = $exception->getMessage();
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }

        $data['liquidation_proof_attachments'] = $attachments;
        $data['liquidation_proof_path'] = $attachments[0]['path'];
        $data['liquidation_proof_uploaded_by'] = $_SESSION['user_name'] ?? 'Requestor';
        $data['liquidation_proof_uploaded_at'] = date('Y-m-d H:i:s');
        db()->prepare('UPDATE forms SET data = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($data), $id]);

        \App\Services\RealtimeEventService::publishPendingCountChanged();
        $_SESSION['success'] = 'Expense liquidation attachments uploaded successfully.';
        header('Location: ' . url('forms/view/' . $id));
        exit;
    }

    private function saveProofAttachments(array $upload, string $relativeDirectory, int $formId, string $label): array {
        $names = $upload['name'] ?? [];
        if (!is_array($names)) {
            foreach ($upload as $key => $value) {
                $upload[$key] = [$value];
            }
            $names = $upload['name'] ?? [];
        }

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
        ];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $filesToSave = [];

        foreach ($names as $index => $originalName) {
            $error = $upload['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('An ' . $label . ' attachment could not be uploaded. Please try again.');
            }

            $temporaryPath = $upload['tmp_name'][$index] ?? '';
            $fileSize = (int)($upload['size'][$index] ?? 0);
            $mimeType = $temporaryPath !== '' ? $finfo->file($temporaryPath) : false;
            if (!isset($allowedTypes[$mimeType])) {
                throw new \RuntimeException('Only JPG, PNG, GIF, and PDF files are allowed for ' . $label . '.');
            }
            if ($fileSize > 20 * 1024 * 1024) {
                throw new \RuntimeException('Each ' . $label . ' attachment must be under 20 MB.');
            }

            $filesToSave[] = [
                'temporary_path' => $temporaryPath,
                'name' => basename(str_replace('\\', '/', (string)$originalName)),
                'extension' => $allowedTypes[$mimeType],
            ];
        }

        if (!$filesToSave) {
            throw new \RuntimeException('Please select at least one ' . $label . ' attachment.');
        }

        $destinationDirectory = __DIR__ . '/../../public/' . $relativeDirectory;
        if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0755, true) && !is_dir($destinationDirectory)) {
            throw new \RuntimeException('Unable to prepare the ' . $label . ' upload directory.');
        }

        $savedAttachments = [];
        foreach ($filesToSave as $file) {
            $fileName = sprintf('%d_%s.%s', $formId, bin2hex(random_bytes(12)), $file['extension']);
            $relativePath = $relativeDirectory . $fileName;
            if (!move_uploaded_file($file['temporary_path'], $destinationDirectory . $fileName)) {
                foreach ($savedAttachments as $savedAttachment) {
                    @unlink(__DIR__ . '/../../public/' . $savedAttachment['path']);
                }
                throw new \RuntimeException('An ' . $label . ' attachment could not be saved. Please try again.');
            }
            $savedAttachments[] = ['path' => $relativePath, 'name' => $file['name']];
        }

        return $savedAttachments;
    }

    private function renderError(int $code, string $title, string $message): never {
        http_response_code($code);
        $errorCode = $code;
        $errorTitle = $title;
        $errorMessage = $message;
        $pageTitle = "{$code} — {$title}";
        define('BASE_LOADED', true);
        ob_start();
        require __DIR__ . '/../../views/error/error.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../views/layouts/base.php';
        exit;
    }

    /**
     * Turns a form's submitted `data` JSON into a flat list of readable
     * "Label: value" lines, e.g. ['Leave Type: Sick Leave', 'Start Date: ...'].
     * Recurses into nested arrays (like line-item tables) so every field
     * a form type collects shows up, without needing a per-form-type mapping.
     */
    private function flattenFormData($data, string $prefix = ''): array {
        $lines = [];
        if (!is_array($data)) return $lines;

        foreach ($data as $key => $value) {
            $label = is_string($key)
                ? ucwords(str_replace('_', ' ', $key))
                : 'Item ' . ((int) $key + 1);
            $label = $prefix !== '' ? "{$prefix} — {$label}" : $label;

            if (is_array($value)) {
                $lines = array_merge($lines, $this->flattenFormData($value, $label));
            } else {
                $value = is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value;
                $lines[] = "{$label}: {$value}";
            }
        }
        return $lines;
    }

    /** Human-readable "Name — Status (date)" line for one approval step. */
    private function formatApprovalLine(array $a): string {
        $line = $a['approver_name'] . ' — ' . ucfirst($a['status']);
        if ($a['approved_at']) $line .= ' on ' . date('M j, Y g:i A', strtotime($a['approved_at']));
        if (!empty($a['remarks'])) $line .= ' ("' . $a['remarks'] . '")';
        return $line;
    }

    private function streamCsvExport(array $forms, array $approvalsByForm, array $formLabel): void {
        $filename = 'completed-requests-' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel opens accented/special characters correctly.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'Request ID', 'Form Type', 'Employee Code', 'Employee Name',
            'Department', 'Company', 'Date Filed', 'Date Completed',
            'Form Details', 'Approval Trail',
        ]);

        foreach ($forms as $row) {
            $data = json_decode($row['data'] ?? '', true) ?: [];
            $details = implode(' | ', $this->flattenFormData($data));
            $trail = [];
            foreach ($approvalsByForm[(int) $row['id']] ?? [] as $a) {
                $trail[] = $this->formatApprovalLine($a);
            }
            fputcsv($out, [
                $row['id'],
                $formLabel[$row['form_type']] ?? $row['form_type'],
                $row['employee_code'],
                $row['full_name'],
                $row['department'] ?? '',
                $row['company'] ?? '',
                date('Y-m-d H:i', strtotime($row['created_at'])),
                date('Y-m-d H:i', strtotime($row['updated_at'])),
                $details,
                implode(' -> ', $trail),
            ]);
        }
        fclose($out);
    }

    /**
     * Excel does not require a real .xlsx (ZIP+XML) binary — it natively
     * opens an HTML table served with the Excel MIME type / .xls extension.
     * This avoids pulling in PhpSpreadsheet for what's a straightforward
     * tabular export.
     */
    private function streamExcelExport(array $forms, array $approvalsByForm, array $formLabel): void {
        $filename = 'completed-requests-' . date('Y-m-d_His') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
        echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8">';
        echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
        echo '<x:Name>Completed Requests</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>';
        echo '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '</head><body>';
        echo '<table border="1" cellspacing="0" cellpadding="4">';
        echo '<tr>';
        foreach (['Request ID', 'Form Type', 'Employee Code', 'Employee Name', 'Department',
                  'Company', 'Date Filed', 'Date Completed', 'Form Details', 'Approval Trail'] as $h) {
            echo '<th>' . htmlspecialchars($h) . '</th>';
        }
        echo '</tr>';

        foreach ($forms as $row) {
            $data = json_decode($row['data'] ?? '', true) ?: [];
            $details = implode("\n", $this->flattenFormData($data));
            $trail = [];
            foreach ($approvalsByForm[(int) $row['id']] ?? [] as $a) {
                $trail[] = $this->formatApprovalLine($a);
            }
            echo '<tr>';
            echo '<td>' . (int) $row['id'] . '</td>';
            echo '<td>' . htmlspecialchars($formLabel[$row['form_type']] ?? $row['form_type']) . '</td>';
            echo '<td>' . htmlspecialchars($row['employee_code']) . '</td>';
            echo '<td>' . htmlspecialchars($row['full_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['department'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($row['company'] ?? '') . '</td>';
            echo '<td>' . date('Y-m-d H:i', strtotime($row['created_at'])) . '</td>';
            echo '<td>' . date('Y-m-d H:i', strtotime($row['updated_at'])) . '</td>';
            echo '<td style="mso-number-format:\'\@\';white-space:pre-wrap;">' . nl2br(htmlspecialchars($details)) . '</td>';
            echo '<td>' . htmlspecialchars(implode(' -> ', $trail)) . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
    }
 
    /**
     * Word does not require a real .docx (ZIP+XML) binary either — it opens
     * an HTML document served with the Word MIME type / .doc extension.
     * Unlike the CSV/Excel table, this lays out one full section per form
     * (all submitted fields + approval trail), since that reads far better
     * as a document than as spreadsheet rows.
     */
    private function streamWordExport(array $forms, array $approvalsByForm, array $formLabel): void {
        $filename = 'completed-requests-' . date('Y-m-d_His') . '.doc';
        header('Content-Type: application/msword; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
        echo $this->buildExportHtml($forms, $approvalsByForm, $formLabel);
    }

    /**
     * Word/Excel can be tricked with an HTML file, but a real PDF can't —
     * PHP has no built-in way to rasterize/paginate to PDF without a library
     * (e.g. dompdf/dompdf via Composer). Rather than mislabel an HTML file
     * as .pdf, this opens a print-ready page and auto-triggers the browser's
     * print dialog so the admin can choose "Save as PDF" there.
     */
    private function streamPrintableExport(array $forms, array $approvalsByForm, array $formLabel): void {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->buildExportHtml($forms, $approvalsByForm, $formLabel, true);
    }

    private function buildExportHtml(array $forms, array $approvalsByForm, array $formLabel, bool $autoPrint = false): string {
        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Completed Requests Export</title>
<style>
    body { font-family: Calibri, Arial, sans-serif; color: #1a1a1a; max-width: 900px; margin: 2rem auto; }
    h1 { font-size: 20px; margin-bottom: 4px; }
    .meta { color: #666; font-size: 13px; margin-bottom: 1.5rem; }
    .form-section { page-break-inside: avoid; border-top: 2px solid #1a1a1a; padding: 1rem 0; }
    .form-section h2 { font-size: 16px; margin: 0 0 4px; }
    .form-meta { font-size: 13px; color: #444; margin-bottom: 0.75rem; }
    h3 { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #555; margin: .75rem 0 .25rem; }
    ul { margin: 0; padding-left: 1.25rem; font-size: 13px; }
    li { margin-bottom: 2px; }
    @media print { .form-section { break-inside: avoid; } }
</style>
</head>
<body<?= $autoPrint ? ' onload="window.print()"' : '' ?>>
    <h1>Completed Requests Export</h1>
    <div class="meta">Generated <?= date('F j, Y g:i A') ?> — <?= count($forms) ?> request(s)</div>

    <?php foreach ($forms as $row):
        $data = json_decode($row['data'] ?? '', true) ?: [];
    ?>
    <div class="form-section">
        <h2>#<?= (int) $row['id'] ?> — <?= htmlspecialchars($formLabel[$row['form_type']] ?? $row['form_type']) ?></h2>
        <div class="form-meta">
            <strong><?= htmlspecialchars($row['full_name']) ?></strong> (<?= htmlspecialchars($row['employee_code']) ?>)
            &nbsp;·&nbsp; <?= htmlspecialchars($row['department'] ?? '—') ?>
            &nbsp;·&nbsp; <?= htmlspecialchars($row['company'] ?? '—') ?><br>
            Filed <?= date('M j, Y g:i A', strtotime($row['created_at'])) ?>
            &nbsp;·&nbsp; Completed <?= date('M j, Y g:i A', strtotime($row['updated_at'])) ?>
        </div>

        <h3>Form Details</h3>
        <ul>
            <?php foreach ($this->flattenFormData($data) as $line): ?>
                <li><?= htmlspecialchars($line) ?></li>
            <?php endforeach; ?>
        </ul>

        <h3>Approval Trail</h3>
        <ul>
            <?php foreach ($approvalsByForm[(int) $row['id']] ?? [] as $a): ?>
                <li><?= htmlspecialchars($this->formatApprovalLine($a)) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    private function audit(string $action, string $entity, int $entityId, ?array $old, ?array $new): void {
        db()->prepare(
            'INSERT INTO audit_logs (performed_by, action, entity_type, entity_id, old_values, new_values, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $_SESSION['user_id'], $action, $entity, $entityId,
            $old ? json_encode($old) : null,
            $new ? json_encode($new) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    private function resolveType(string $slug): string {
        if (!isset($this->typeMap[$slug])) return $this->renderError(404, 'Not Found', 'Unknown form type.');
        return $this->typeMap[$slug];
    }

    // AcquisitionChecker/Accounting (role 5) may edit a form while it is
    // sitting at their Process (Accounting Checking) stage — i.e. the form's
    // current status matches the 'from' status of the pipeline step whose
    // role_id is 5 for this form's type. Derived from the pipeline
    // definition so it stays correct if the pipeline ever changes.
    private function canEditAsProcessApprover(array $form): bool {
        if ((int) ($_SESSION['role_id'] ?? 0) !== 5) return false;

        // Pipeline-defined stage (Finance forms: advance_payment, request_for_payment)
        $pipeline = $this->getPipeline($form['form_type'], $form['submitter_department'] ?? null, (int) $form['submitted_by'], (int) $form['id']);
        foreach ($pipeline as $step) {
            if ((int) $step['role_id'] === 5 && $form['status'] === $step['from']) {
                return true;
            }
        }

        // Legacy Liquidation co-sign row: Accounting is also seeded at sequence 3.
        if ($form['form_type'] === 'liquidation'
            && $form['status'] === 'immediatehead_approved'
        ) {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            $row = db()->prepare(
                "SELECT id FROM approvals
                WHERE form_id = ? AND approver_id = ? AND sequence = 3 AND status = 'pending'
                LIMIT 1"
            );
            $row->execute([$form['id'], $userId]);
            if ($row->fetch()) return true;
        }

        return false;
    }

    public function edit(int $id): void {
        $form = $this->findForm($id);

        $userId = (int) $_SESSION['user_id'];
        $roleId = (int) $_SESSION['role_id'];
        $isOwner = $roleId === 1 || (int) $form['submitted_by'] === $userId;
        $isProcessApprover = $this->canEditAsProcessApprover($form);

        if (!$isOwner && !$isProcessApprover) {
            $this->renderError(403, 'Access Denied', 'You can only edit your own submissions.');
        }

        $editableStatuses = ['draft', 'submitted', 'rejected'];
        if (!$isProcessApprover && !in_array($form['status'], $editableStatuses, true)) {
            $_SESSION['error'] = 'This form cannot be edited while it is in approval or completed.';
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }

        $data = json_decode($form['data'], true) ?? [];
        $type = $form['form_type'];
        $slug = array_search($type, $this->typeMap) ?: $type;
        $formLabel = \App\Helpers\FormLabels::all();
        $typeLabel = \App\Helpers\FormLabels::get($type);
        $pageTitle = 'Edit ' . $typeLabel . ' #' . $id;
        $currentUser = $_SESSION['user_name'] ?? '';        
        $currentDept = $_SESSION['department'] ?? '';        
        $departments = db()->query('SELECT name FROM departments ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

        $breadcrumbs = [
            ['label' => $typeLabel, 'url' => url('forms/' . $slug)],
            ['label' => '#' . $id,  'url' => url('forms/view/' . $id)],
            ['label' => 'Edit'],
        ];

        $noSuffix = ['list', 'show', 'request_for_payment'];
        $viewName = in_array($type, $noSuffix) ? $type : "{$type}_form";

        $this->render("forms/{$viewName}", compact(
            'form', 'data', 'type', 'slug', 'formLabel', 'typeLabel',
            'pageTitle', 'breadcrumbs', 'departments',
            'currentUser', 'currentDept'              
        ));
    }

    public function update(int $id): void {
        \App\Helpers\Csrf::verify();

        $form = $this->findForm($id);

        $userId = (int) $_SESSION['user_id'];
        $roleId = (int) $_SESSION['role_id'];
        $isOwner = $roleId === 1 || (int) $form['submitted_by'] === $userId;
        $isProcessApprover = $this->canEditAsProcessApprover($form);

        if (!$isOwner && !$isProcessApprover) {
            $this->renderError(403, 'Access Denied', 'You can only edit your own submissions.');
        }

        $editableStatuses = ['draft', 'submitted', 'rejected'];
        if (!$isProcessApprover && !in_array($form['status'], $editableStatuses, true)) {
            $_SESSION['error'] = 'This form cannot be edited in its current status.';
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }

        $type = $form['form_type'];
        $fields = $this->fields[$type] ?? [];
        $data = [];

        foreach ($fields as $field) {
            $val = $_POST[$field] ?? '';
            if (is_string($val)) $val = trim($val);
            if ($val === '' || (is_array($val) && empty(array_filter($val)))) {
                $_SESSION['error'] = "Field '{$field}' is required.";
                header('Location: ' . url('forms/' . $id . '/edit'));
                exit;
            }
        }

        foreach ($_POST as $key => $val) {
            if (in_array($key, ['csrf_token', 'save_draft', 'existing_attachments'], true)) continue;
            if (is_array($val)) {
                $data[$key] = array_map(fn($v) => htmlspecialchars(trim($v), ENT_QUOTES), $val);
            } else {
                $data[$key] = htmlspecialchars(trim($val), ENT_QUOTES);
            }
        }

        $existing = array_values(array_filter($_POST['existing_attachments'] ?? []));

        if (!empty($_FILES['attachments']['name'][0])) {
            $allowed = ['image/jpeg', 'image/png', 'application/pdf'];
            $maxBytes = 20 * 1024 * 1024;
            $destDir = __DIR__ . '/../../public/uploads/forms/';
            if (!is_dir($destDir)) mkdir($destDir, 0755, true);
            $finfo = new \finfo(FILEINFO_MIME_TYPE);

            foreach ($_FILES['attachments']['tmp_name'] as $i => $tmp) {
                if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $mime = $finfo->file($tmp);
                if (!in_array($mime, $allowed, true) || $_FILES['attachments']['size'][$i] > $maxBytes) {
                    $_SESSION['error'] = 'Invalid file or file exceeds 20 MB.';
                    header('Location: ' . url('forms/' . $id . '/edit'));
                    exit;
                }
                $ext = pathinfo($_FILES['attachments']['name'][$i], PATHINFO_EXTENSION);
                $fileName = sprintf('%s_%s.%s', time(), bin2hex(random_bytes(4)), $ext);
                move_uploaded_file($tmp, $destDir . $fileName);
                $existing[] = 'uploads/forms/' . $fileName;
            }
        }

        if (!empty($existing)) $data['attachments'] = $existing;

        $old = json_decode($form['data'], true) ?? [];
        $isDraftSubmission = $form['status'] === 'draft' && empty($_POST['save_draft']);
        $isResubmitting = ($form['status'] === 'rejected' || $isDraftSubmission) && empty($_POST['save_draft']);
        $isSavingRejectedDraft = $form['status'] === 'rejected' && !empty($_POST['save_draft']);

        try {
            $pdo = db();
            if ($isResubmitting) {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM approvals WHERE form_id = ?')->execute([$id]);
                $pdo->prepare(
                    "UPDATE forms SET data = ?, status = 'submitted', updated_at = NOW() WHERE id = ?"
                )->execute([json_encode($data), $id]);
                $submitterDepartment = $form['submitter_department'] ?? null;
                $this->seedApprovalRows($pdo, $id, $type, $data, $userId, $submitterDepartment);
                $this->initializeSubmittedApprovals($pdo, $id, $type, $submitterDepartment);
                $this->audit('form_resubmitted', 'form', $id,
                    ['status' => $form['status'], 'data' => $old],
                    ['status' => 'submitted', 'data' => $data]
                );
                $pdo->commit();
                \App\Services\RealtimeEventService::publishPendingCountChanged();
                $_SESSION['success'] = $form['status'] === 'draft'
                    ? 'Draft submitted successfully and sent to approval.'
                    : 'Form resubmitted successfully and returned to the approval process.';
            } elseif ($isSavingRejectedDraft || (!empty($_POST['save_draft']) && $form['status'] === 'draft')) {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM approvals WHERE form_id = ?')->execute([$id]);
                $pdo->prepare(
                    "UPDATE forms SET data = ?, status = 'draft', updated_at = NOW() WHERE id = ?"
                )->execute([json_encode($data), $id]);
                $this->audit('form_saved_as_draft', 'form', $id,
                    ['status' => $form['status'], 'data' => $old],
                    ['status' => 'draft', 'data' => $data]
                );
                $pdo->commit();
                \App\Services\RealtimeEventService::publishPendingCountChanged();
                $_SESSION['success'] = 'This form is already saved in Drafts.';
            } else {
                $pdo->prepare('UPDATE forms SET data = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([json_encode($data), $id]);
                $this->audit('form_edited', 'form', $id, $old, $data);
                $_SESSION['success'] = 'Form updated successfully.';
            }
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error'] = 'Update failed. Please try again.';
        }

        $redirect = !empty($_POST['save_draft']) ? url('dashboard') : url('forms/view/' . $id);
        header('Location: ' . $redirect);
        exit;
    }

    public function delete(int $id): void {
        \App\Helpers\Csrf::verify();

        $form = $this->findForm($id);

        if ($_SESSION['role_id'] != 1 && $form['submitted_by'] != $_SESSION['user_id']) {
            $this->renderError(403, 'Access Denied', 'You can only delete your own submissions.');
        }

        $deletableStatuses = ['draft', 'submitted', 'rejected'];
        if (!in_array($form['status'], $deletableStatuses, true)) {
            $_SESSION['error'] = 'This form cannot be deleted while it is in approval or completed.';
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM approvals WHERE form_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM forms WHERE id = ?')->execute([$id]);
            $this->audit('form_deleted', 'form', $id, ['type' => $form['form_type'], 'status' => $form['status']], null);
            $pdo->commit();
            \App\Services\RealtimeEventService::publishPendingCountChanged();
            $_SESSION['success'] = $form['status'] === 'rejected'
                ? 'Rejected request #' . $id . ' has been deleted.'
                : 'Form #' . $id . ' has been deleted.';
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Delete failed. Please try again.';
            header('Location: ' . url('forms/view/' . $id));
            exit;
        }

        header('Location: ' . url('dashboard'));
        exit;
    }

    private function render(string $view, array $vars = []): void {
        $allowed = [
            'forms/list', 'forms/show',
            'forms/advance_payment_form', 'forms/overtime_authorization_form',
            'forms/request_for_payment', 'forms/leave_application_form',
            'forms/reimbursement_form', 'forms/liquidation_form',
            'forms/vehicle_request_form',
        ];
        if (!in_array($view, $allowed, true)) {
            $this->renderError(404, 'Not Found', 'The requested view does not exist.');
        }
        $basePath = realpath(__DIR__ . '/../../views');
        $fullPath = realpath($basePath . '/' . $view . '.php');
        if ($fullPath === false || strpos($fullPath, $basePath) !== 0) {
            $this->renderError(403, 'Access Denied', 'You do not have permission to perform this action.');
        }
        define('BASE_LOADED', true);
        extract($vars);
        if (!isset($pageTitle)) $pageTitle = 'Automated Requesting System';
        ob_start();
        require $fullPath;
        $content = ob_get_clean();
        require __DIR__ . '/../../views/layouts/base.php';
    }
}
