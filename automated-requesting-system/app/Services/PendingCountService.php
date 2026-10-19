<?php
    namespace App\Services;

    class PendingCountService {
        public static function normalizeLegacyFinalHrDepartmentRequests(?int $formId = null): void {
            $pdo = db();
            $formFilter = $formId === null ? '' : ' AND f.id = ?';

            $pdo->beginTransaction();
            try {
                $resetStatus = $pdo->prepare(
                    "UPDATE forms f
                     JOIN employees e ON e.id = f.submitted_by
                     JOIN approvals af ON af.form_id = f.id
                       AND af.sequence = 4 AND af.status = 'pending'
                     JOIN employees final_approver ON final_approver.id = af.approver_id
                     SET f.status = 'submitted'
                     WHERE final_approver.role_id = 6
                       AND f.form_type IN ('leave_application', 'overtime_authorization')
                       AND LOWER(e.department) REGEXP '(^|[^[:alnum:]])(management|human[[:space:]]+resources|hr|admin(istration)?)([^[:alnum:]]|$)'
                       AND f.status NOT IN ('draft', 'completed', 'rejected', 'cancelled')
                       {$formFilter}"
                );
                $resetStatus->execute($formId === null ? [] : [$formId]);

                $skipEarlier = $pdo->prepare(
                    "UPDATE approvals a
                     JOIN forms f ON f.id = a.form_id
                     JOIN employees e ON e.id = f.submitted_by
                     JOIN approvals af ON af.form_id = f.id
                       AND af.sequence = 4 AND af.status = 'pending'
                     JOIN employees final_approver ON final_approver.id = af.approver_id
                     SET a.status = 'skipped'
                     WHERE a.status = 'pending'
                       AND a.sequence IN (2, 3)
                       AND final_approver.role_id = 6
                       AND f.form_type IN ('leave_application', 'overtime_authorization')
                       AND LOWER(e.department) REGEXP '(^|[^[:alnum:]])(management|human[[:space:]]+resources|hr|admin(istration)?)([^[:alnum:]]|$)'
                       AND f.status = 'submitted'
                       {$formFilter}"
                );
                $skipEarlier->execute($formId === null ? [] : [$formId]);

                $moveFinal = $pdo->prepare(
                    "UPDATE approvals a_final
                     JOIN forms f ON f.id = a_final.form_id
                     JOIN employees e ON e.id = f.submitted_by
                     JOIN employees final_approver ON final_approver.id = a_final.approver_id
                     SET a_final.sequence = 2
                     WHERE a_final.sequence = 4
                       AND a_final.status = 'pending'
                       AND final_approver.role_id = 6
                       AND f.form_type IN ('leave_application', 'overtime_authorization')
                       AND LOWER(e.department) REGEXP '(^|[^[:alnum:]])(management|human[[:space:]]+resources|hr|admin(istration)?)([^[:alnum:]]|$)'
                       AND f.status = 'submitted'
                       {$formFilter}"
                );
                $moveFinal->execute($formId === null ? [] : [$formId]);
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }

        public static function forUser(int $userId, int $roleId): int {
            VehicleRequestWorkflowService::normalizePendingRequests();

            if ($roleId === 3) {
                return count(PostApprovalService::actionableRowsForUser($userId, $roleId));
            }

            if ($roleId === 6) {
                self::normalizeLegacyFinalHrDepartmentRequests();
            }

            if ($roleId === 1) {
                $stmt = db()->prepare(
                    'SELECT COUNT(DISTINCT a.form_id) FROM approvals a
                     JOIN forms f ON f.id = a.form_id
                     WHERE a.status = "pending"
                     AND f.status NOT IN ("draft", "cancelled", "completed", "rejected")
                     AND NOT EXISTS (
                         SELECT 1 FROM approvals a3
                         WHERE a3.form_id = a.form_id
                           AND a3.status = "pending"
                           AND a3.sequence < a.sequence
                     )'
                );
                $stmt->execute();
            } elseif ($roleId === 7) {
                $stmt = db()->prepare(
                    'SELECT COUNT(DISTINCT a.form_id) FROM approvals a
                     JOIN forms f ON f.id = a.form_id
                     WHERE a.status = "pending"
                     AND f.status NOT IN ("draft", "cancelled", "completed", "rejected")
                     AND (
                         a.approver_id = ?
                         OR (f.form_type = "vehicle_request" AND a.sequence = 4)
                         OR (f.form_type IN ("leave_application", "overtime_authorization") AND a.sequence = 4)
                     )
                     AND NOT EXISTS (
                         SELECT 1 FROM approvals a3
                         WHERE a3.form_id = a.form_id
                           AND a3.status = "pending"
                           AND a3.sequence < a.sequence
                     )'
                );
                $stmt->execute([$userId]);
            } elseif (in_array($roleId, [5, 6, 8, 9], true)) {
                $stmt = db()->prepare(
                    'SELECT COUNT(DISTINCT a.form_id) FROM approvals a
                     JOIN forms f ON f.id = a.form_id
                     JOIN employees approver ON approver.id = a.approver_id
                     WHERE (
                         a.approver_id = ?
                         OR a.approver_id IN (SELECT id FROM employees WHERE role_id = ?)
                         OR (
                             ? = 5
                             AND f.form_type = "liquidation"
                             AND a.sequence = 3
                             AND a.approver_id IN (SELECT id FROM employees WHERE role_id = 9)
                             AND NOT EXISTS (
                                 SELECT 1 FROM approvals a5
                                 JOIN employees e5 ON e5.id = a5.approver_id
                                 WHERE a5.form_id = f.id
                                   AND a5.sequence = 3
                                   AND e5.role_id = 5
                             )
                         )
                     )
                     AND (
                         f.form_type <> "reimbursement"
                         OR (? = 5 AND a.sequence = 4 AND approver.role_id = 5)
                         OR (? = 9 AND a.sequence = 3 AND approver.role_id = 9)
                         OR ? NOT IN (5, 9)
                     )
                     AND a.status = "pending"
                     AND f.status NOT IN ("draft", "cancelled", "completed", "rejected")
                     AND NOT (? = 9 AND f.form_type = "reimbursement"
                              AND a.sequence = 3 AND f.submitted_by = ?)
                     AND NOT EXISTS (
                         SELECT 1 FROM approvals a3
                         JOIN employees e3 ON e3.id = a3.approver_id
                         WHERE a3.form_id = a.form_id
                           AND a3.status = "pending"
                           AND a3.sequence < a.sequence
                     )'
                );
                $stmt->execute([$userId, $roleId, $roleId, $roleId, $roleId, $roleId, $roleId, $userId]);
            } else {
                $stmt = db()->prepare(
                    'SELECT COUNT(DISTINCT a.form_id) FROM approvals a
                     JOIN forms f ON f.id = a.form_id
                     WHERE a.approver_id = ? AND a.status = "pending"
                     AND f.status NOT IN ("draft", "cancelled", "completed", "rejected")
                     AND NOT EXISTS (
                         SELECT 1 FROM approvals a3
                         JOIN employees e3 ON e3.id = a3.approver_id
                         WHERE a3.form_id = a.form_id
                           AND a3.status = "pending"
                           AND a3.sequence < a.sequence
                           AND e3.role_id <> 8
                     )'
                );
                $stmt->execute([$userId]);
            }

            $pendingCount = (int) $stmt->fetchColumn();
            return $pendingCount + count(PostApprovalService::actionableRowsForUser($userId, $roleId));
        }
    }