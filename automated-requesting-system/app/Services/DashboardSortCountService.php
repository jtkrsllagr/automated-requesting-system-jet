<?php
    namespace App\Services;

    class DashboardSortCountService {
        public static function completedForUser(int $userId, int $roleId): int {
            $stmt = db()->prepare(
                "SELECT COUNT(*) FROM forms f
                 WHERE f.status = 'completed'
                   AND (
                      ? = 6
                     OR f.form_type = 'vehicle_request'
                     OR f.form_type IN ('leave_application', 'overtime_authorization')
                      OR (
                          f.form_type = 'reimbursement'
                          AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(f.data, '$.payment_proof_path')), '') <> ''
                       )
                       OR (
                           f.form_type NOT IN ('leave_application', 'overtime_authorization', 'reimbursement')
                           AND
                           COALESCE(JSON_UNQUOTE(JSON_EXTRACT(f.data, '$.payment_proof_path')), '') <> ''
                           AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(f.data, '$.liquidation_proof_path')), '') <> ''
                       )
                   )
                   AND (
                       (f.form_type = 'advance_payment' AND (
                           ? IN (1, 6, 7)
                           OR f.submitted_by = ?
                           OR EXISTS (
                               SELECT 1 FROM approvals advance_payment_approval
                               WHERE advance_payment_approval.form_id = f.id
                                 AND advance_payment_approval.approver_id = ?
                           )
                           OR EXISTS (
                               SELECT 1 FROM employees requester
                               WHERE requester.id = f.submitted_by
                                 AND requester.supervisor_id_2 = ?
                           )
                       ))
                       OR (f.form_type <> 'advance_payment'
                           AND (? IN (1, 6, 7)
                               OR (? = 9 AND f.form_type IN ('reimbursement', 'leave_application', 'overtime_authorization'))
                               OR EXISTS (
                               SELECT 1 FROM approvals a
                               WHERE a.form_id = f.id AND a.approver_id = ?
                           )))
                   )
                   AND (
                       ? <> 2
                       OR (
                           f.submitted_by = ?
                           OR (
                               EXISTS (
                                   SELECT 1 FROM approvals immediate_head_process
                                   WHERE immediate_head_process.form_id = f.id
                                     AND immediate_head_process.approver_id = ?
                               )
                               AND EXISTS (
                                   SELECT 1 FROM employees requester
                                   WHERE requester.id = f.submitted_by
                                     AND (
                                         requester.supervisor_id = ?
                                         OR (f.form_type = 'vehicle_request' AND requester.supervisor_id_2 = ?)
                                     )
                               )
                           )
                       )
                   )"
            );
            $stmt->execute([$roleId, $roleId, $userId, $userId, $userId, $roleId, $roleId, $userId, $roleId, $userId, $userId, $userId, $userId]);

            return (int) $stmt->fetchColumn();
        }

        public static function approvedForUser(int $userId, int $roleId): int {
            $nonAdvanceScope = match ($roleId) {
                1, 6, 7 => '1 = 1',
                5, 8 => 'f.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND (f.submitted_by = ?
                         OR EXISTS (SELECT 1 FROM approvals mine WHERE mine.form_id = f.id AND mine.approver_id = ?)
                         OR EXISTS (
                             SELECT 1 FROM approvals peer_approval
                             JOIN employees peer ON peer.id = peer_approval.approver_id
                             WHERE peer_approval.form_id = f.id AND peer.role_id = ?
                         ))',
                9 => '(f.form_type IN (\'reimbursement\', \'leave_application\', \'overtime_authorization\')
                    OR (f.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        AND (f.submitted_by = ?
                             OR EXISTS (SELECT 1 FROM approvals mine WHERE mine.form_id = f.id AND mine.approver_id = ?)
                             OR EXISTS (
                                 SELECT 1 FROM approvals peer_approval
                                 JOIN employees peer ON peer.id = peer_approval.approver_id
                                 WHERE peer_approval.form_id = f.id AND peer.role_id = ?
                             ))))',
                2, 4 => 'f.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND (f.submitted_by = ?
                         OR EXISTS (SELECT 1 FROM approvals mine WHERE mine.form_id = f.id AND mine.approver_id = ?))',
                default => 'f.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND f.submitted_by = ?',
            };

            $params = [$roleId, $userId, $userId, $userId];
            if (in_array($roleId, [5, 8, 9], true)) {
                array_push($params, $userId, $userId, $roleId);
            } elseif (in_array($roleId, [2, 4], true)) {
                array_push($params, $userId, $userId);
            } elseif (!in_array($roleId, [1, 6, 7], true)) {
                $params[] = $userId;
            }

            $stmt = db()->prepare(
                "SELECT COUNT(*) FROM forms f
                 WHERE f.status IN ('final_approved', 'completed')
                   AND (
                       (f.form_type = 'advance_payment' AND (
                           ? IN (1, 6, 7)
                           OR f.submitted_by = ?
                           OR EXISTS (
                               SELECT 1 FROM approvals advance_payment_approval
                               WHERE advance_payment_approval.form_id = f.id
                                 AND advance_payment_approval.approver_id = ?
                           )
                           OR EXISTS (
                               SELECT 1 FROM employees requester
                               WHERE requester.id = f.submitted_by
                                 AND requester.supervisor_id_2 = ?
                           )
                       ))
                       OR (f.form_type <> 'advance_payment' AND ({$nonAdvanceScope}))
                   )
                   AND (
                       ? <> 2
                       OR (
                           f.submitted_by = ?
                           OR (
                               EXISTS (
                                   SELECT 1 FROM approvals immediate_head_process
                                   WHERE immediate_head_process.form_id = f.id
                                     AND immediate_head_process.approver_id = ?
                               )
                               AND EXISTS (
                                   SELECT 1 FROM employees requester
                                   WHERE requester.id = f.submitted_by
                                     AND (
                                         requester.supervisor_id = ?
                                         OR (f.form_type = 'vehicle_request' AND requester.supervisor_id_2 = ?)
                                     )
                               )
                           )
                       )
                   )"
            );
            array_push($params, $roleId, $userId, $userId, $userId, $userId);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }
    }
