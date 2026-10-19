<?php
    namespace App\Services;

    class VehicleRequestWorkflowService {
        public static function normalizePendingRequests(?int $formId = null): void {
            $formFilter = $formId === null ? '' : ' AND f.id = ?';
            $stmt = db()->prepare(
                "SELECT DISTINCT f.id
                 FROM forms f
                 JOIN approvals a ON a.form_id = f.id AND a.status = 'pending'
                 WHERE f.form_type = 'vehicle_request'
                   AND f.status NOT IN ('draft', 'cancelled', 'completed', 'rejected')
                   AND a.sequence IN (3, 4)
                   {$formFilter}"
            );
            $stmt->execute($formId === null ? [] : [$formId]);
            $formIds = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

            foreach ($formIds as $pendingFormId) {
                self::normalizePendingRequest($pendingFormId);
            }
        }

        private static function normalizePendingRequest(int $formId): void {
            $pdo = db();
            $pdo->beginTransaction();

            try {
                $formStmt = $pdo->prepare(
                    "SELECT status FROM forms
                     WHERE id = ? AND form_type = 'vehicle_request'
                     FOR UPDATE"
                );
                $formStmt->execute([$formId]);
                $status = $formStmt->fetchColumn();
                if ($status === false || in_array($status, ['draft', 'cancelled', 'completed', 'rejected'], true)) {
                    $pdo->commit();
                    return;
                }

                $supervisorStep = $pdo->prepare(
                    'SELECT COUNT(*) FROM approvals WHERE form_id = ? AND sequence = 2'
                );
                $supervisorStep->execute([$formId]);
                $hasSupervisorStep = (int) $supervisorStep->fetchColumn() > 0;

                if (!$hasSupervisorStep) {
                    $pdo->prepare(
                        "UPDATE forms SET status = 'submitted', updated_at = NOW()
                         WHERE id = ? AND status = 'immediatehead_approved'
                           AND EXISTS (
                               SELECT 1 FROM approvals
                               WHERE form_id = ? AND sequence = 3 AND status = 'pending'
                           )"
                    )->execute([$formId, $formId]);
                }

                self::normalizeStageApprover($pdo, $formId, 3, 2, true);
                self::normalizeStageApprover($pdo, $formId, 4, 7, false);
                $pdo->commit();
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log(sprintf(
                    '[VehicleRequestWorkflowService] Could not normalize pending request %d: %s',
                    $formId,
                    $exception->getMessage()
                ));
            }
        }

        private static function normalizeStageApprover(
            \PDO $pdo,
            int $formId,
            int $sequence,
            int $roleId,
            bool $adminDepartmentOnly
        ): void {
            $departmentFilter = $adminDepartmentOnly
                ? "AND LOWER(e.department) REGEXP '(^|[^[:alnum:]])admin(istration)?([^[:alnum:]]|$)'"
                : '';
            $pendingStmt = $pdo->prepare(
                "SELECT a.id, a.approver_id
                 FROM approvals a
                 JOIN employees e ON e.id = a.approver_id
                 WHERE a.form_id = ? AND a.sequence = ? AND a.status = 'pending'
                   AND e.role_id = ? AND e.is_active = 1
                   {$departmentFilter}
                 LIMIT 1"
            );
            $pendingStmt->execute([$formId, $sequence, $roleId]);
            if ($pendingStmt->fetchColumn() !== false) {
                return;
            }

            $approverStmt = $pdo->prepare(
                "SELECT e.id
                 FROM employees e
                 LEFT JOIN approvals workload ON workload.approver_id = e.id AND workload.status = 'pending'
                 WHERE e.role_id = ? AND e.is_active = 1
                   {$departmentFilter}
                   AND NOT EXISTS (
                       SELECT 1 FROM approvals existing
                       WHERE existing.form_id = ? AND existing.sequence = ?
                         AND existing.approver_id = e.id
                   )
                 GROUP BY e.id
                 ORDER BY COUNT(workload.id) ASC, e.id ASC
                 LIMIT 1"
            );
            $approverStmt->execute([$roleId, $formId, $sequence]);
            $approverId = $approverStmt->fetchColumn();
            if ($approverId === false) {
                error_log(sprintf(
                    '[VehicleRequestWorkflowService] No active role %d approver for request %d stage %d.',
                    $roleId,
                    $formId,
                    $sequence
                ));
                return;
            }

            $pendingRows = $pdo->prepare(
                "SELECT id FROM approvals
                 WHERE form_id = ? AND sequence = ? AND status = 'pending'
                 ORDER BY id"
            );
            $pendingRows->execute([$formId, $sequence]);
            $approvalIds = array_map('intval', $pendingRows->fetchAll(\PDO::FETCH_COLUMN));
            if (empty($approvalIds)) {
                return;
            }

            $pdo->prepare(
                'UPDATE approvals SET approver_id = ? WHERE id = ? AND status = \'pending\''
            )->execute([(int) $approverId, $approvalIds[0]]);
            if (count($approvalIds) > 1) {
                $skip = $pdo->prepare(
                    "UPDATE approvals SET status = 'skipped', remarks = 'Superseded by Vehicle Request workflow update'
                     WHERE id = ? AND status = 'pending'"
                );
                foreach (array_slice($approvalIds, 1) as $approvalId) {
                    $skip->execute([$approvalId]);
                }
            }
        }
    }
