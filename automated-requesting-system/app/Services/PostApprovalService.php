<?php
    namespace App\Services;

    class PostApprovalService {
        public static function countForUser(int $userId, int $roleId): int {
            return count(self::rowsForUser($userId, $roleId));
        }

        public static function actionableRowsForUser(int $userId, int $roleId): array {
            return array_values(array_filter(
                self::rowsForUser($userId, $roleId),
                static fn(array $row): bool => !empty($row['can_process'])
            ));
        }

        public static function countDisbursementActionsForUser(int $userId, int $roleId): int {
            if ($roleId !== 5) return 0;

            $stmt = db()->prepare(
                "SELECT COUNT(*) FROM forms f
                 WHERE f.status = 'completed'
                   AND f.form_type <> 'vehicle_request'
                   AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(f.data, '$.payment_proof_path')), '') = ''"
            );
            $stmt->execute();

            return (int) $stmt->fetchColumn();
        }

        public static function rowsForUser(int $userId, int $roleId): array {
            $paymentMissing = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(f.data, '$.payment_proof_path')), '') = ''";
            $liquidationMissing = "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(f.data, '$.liquidation_proof_path')), '') = ''";
            $visibilityScope = in_array($roleId, [1, 6, 7], true)
                ? '1 = 1'
                : "((f.form_type <> 'advance_payment'
                    AND (? IN (1, 5, 6) OR f.submitted_by = ?
                         OR EXISTS (
                             SELECT 1 FROM approvals a
                             WHERE a.form_id = f.id AND a.approver_id = ?
                         )))
                   OR (f.form_type = 'advance_payment'
                       AND (f.submitted_by = ?
                            OR EXISTS (
                                SELECT 1 FROM approvals advance_payment_approval
                                WHERE advance_payment_approval.form_id = f.id
                                  AND advance_payment_approval.approver_id = ?
                            )
                            OR EXISTS (
                                SELECT 1 FROM employees requester
                                WHERE requester.id = f.submitted_by
                                  AND requester.supervisor_id_2 = ?
                            ))))";
            $postApprovalAction = "CASE
                       WHEN f.form_type = 'reimbursement'
                           THEN 'Disbursement Confirmation'
                       WHEN {$paymentMissing} AND {$liquidationMissing}
                           THEN 'Disbursement Confirmation and Expense Liquidation'
                       WHEN {$paymentMissing} THEN 'Disbursement Confirmation'
                       ELSE 'Expense Liquidation'
                   END";
            $postApprovalEligibility = "f.form_type NOT IN ('leave_application', 'overtime_authorization')
                   AND f.form_type <> 'vehicle_request'
                   AND (
                       (f.form_type = 'reimbursement' AND {$paymentMissing})
                       OR (f.form_type <> 'reimbursement' AND ({$paymentMissing} OR {$liquidationMissing}))
                   )";
            $sql = "SELECT f.id, f.form_type, f.status, f.submitted_by, e.full_name, f.created_at,
                           {$postApprovalAction} AS post_approval_action
                    FROM forms f
                    JOIN employees e ON e.id = f.submitted_by
                    WHERE {$visibilityScope}
                      AND f.status = 'completed'
                      AND {$postApprovalEligibility}
                    ORDER BY created_at DESC, id DESC";

            $stmt = db()->prepare($sql);
            if (in_array($roleId, [1, 6, 7], true)) {
                $stmt->execute();
            } else {
                $stmt->execute([$roleId, $userId, $userId, $userId, $userId, $userId]);
            }

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['can_process'] = (
                    ($roleId === 5 && str_contains($row['post_approval_action'], 'Disbursement Confirmation'))
                    || (($roleId === 1 || (int) $row['submitted_by'] === $userId)
                        && str_contains($row['post_approval_action'], 'Expense Liquidation')
                        && !str_contains($row['post_approval_action'], 'Disbursement Confirmation'))
                );
            }
            unset($row);

            return $rows;
        }
    }
