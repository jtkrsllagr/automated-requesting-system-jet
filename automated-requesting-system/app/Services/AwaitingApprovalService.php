<?php
    namespace App\Services;

    class AwaitingApprovalService {
        public static function countForUser(int $userId, int $roleId): int {
            if ($roleId === 3) {
                $stmt = db()->prepare(
                    "SELECT COUNT(*) FROM forms
                     WHERE submitted_by = ?
                       AND status IN ('submitted', 'immediatehead_approved', 'process_approved', 'department_reviewed', 'finance_reviewed')"
                );
                $stmt->execute([$userId]);
                return (int) $stmt->fetchColumn();
            }

            [$sql, $params] = self::query('COUNT(*)', [$userId, $roleId]);
            $stmt = db()->prepare($sql);
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }

        public static function rowsForUser(int $userId, int $roleId): array {
            if ($roleId === 3) {
                                $stmt = db()->prepare(
                                    "SELECT 1 AS can_view, 0 AS can_process, f.id, f.form_type, f.status, f.submitted_by,
                                                        e.full_name, f.created_at
                                         FROM forms f
                                         JOIN employees e ON e.id = f.submitted_by
                                         WHERE f.submitted_by = ?
                                             AND f.status IN ('submitted', 'immediatehead_approved', 'process_approved', 'department_reviewed', 'finance_reviewed')
                                         ORDER BY f.created_at DESC"
                                );
                                $stmt->execute([$userId]);
                                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            $viewability = 'CASE WHEN ' . (int) ($params[1] ?? 0) . ' IN (1, 6) OR ('
                    . (int) ($params[1] ?? 0) . ' = 9 AND f.form_type IN (\'reimbursement\', \'leave_application\', \'overtime_authorization\'))
                    OR ('
                    . (int) ($params[1] ?? 0) . ' = 7 AND f.form_type = \'advance_payment\')
                    OR (' . (int) ($params[1] ?? 0) . ' = 7 AND f.form_type = \'leave_application\'
                         AND LOWER(e.department) REGEXP \'(^|[^[:alnum:]])admin(istration)?([^[:alnum:]]|$)\')
                    OR (' . (int) ($params[1] ?? 0) . ' IN (6, 7) AND f.form_type = \'vehicle_request\')
                    OR (' . (int) ($params[1] ?? 0) . ' = 5 AND f.form_type IN (\'advance_payment\', \'reimbursement\'))
                    OR f.submitted_by = ? OR ? = 1
                    OR EXISTS (SELECT 1 FROM approvals mine WHERE mine.form_id = f.id AND mine.approver_id = ?)
                    OR (f.form_type = \'advance_payment\' AND EXISTS (
                        SELECT 1 FROM employees requester
                        WHERE requester.id = f.submitted_by AND requester.supervisor_id_2 = ?
                    ))
                    OR EXISTS (
                        SELECT 1 FROM approvals a
                        JOIN employees approver ON approver.id = a.approver_id
                        WHERE a.form_id = f.id AND a.status = \'pending\'
                                                AND NOT EXISTS (
                                                        SELECT 1 FROM approvals a3
                                                        WHERE a3.form_id = a.form_id
                                                            AND a3.status = \'pending\'
                                                            AND a3.sequence < a.sequence
                                                            AND a3.approver_id <> a.approver_id
                                                )
                        AND (
                            (? = 4 AND a.sequence = 2)
                            OR (? = 5 AND (
                                approver.role_id = 5
                                OR (f.form_type = \'liquidation\'
                                    AND a.sequence = 3 AND approver.role_id = 9
                                    AND NOT EXISTS (
                                        SELECT 1 FROM approvals a5
                                        JOIN employees e5 ON e5.id = a5.approver_id
                                        WHERE a5.form_id = f.id AND a5.sequence = 3 AND e5.role_id = 5
                                    ))
                            ))
                            OR (? IN (6, 8, 9) AND approver.role_id = ?)
                            OR (? = 7 AND (
                                (f.form_type = \'vehicle_request\' AND a.sequence IN (2, 3, 4))
                                OR (f.form_type IN (\'leave_application\', \'overtime_authorization\') AND a.sequence = 4)
                            ))
                        )
                    )
                    OR (? IN (6, 9) AND f.form_type IN (\'leave_application\', \'overtime_authorization\')
                        AND LOWER(e.department) REGEXP \'(^|[^[:alnum:]])(management|human[[:space:]]+resources|hr|admin(istration)?)([^[:alnum:]]|$)\')
                    THEN 1 ELSE 0 END AS can_view';
            $standInAction = match ($roleId) {
                1 => '1 = 1',
                4 => 'a.sequence = 2',
                5 => '((approver.role_id = 5 AND (f.form_type <> \'reimbursement\' OR a.sequence = 4)) OR (f.form_type = \'liquidation\' AND approver.role_id = 9))',
                6, 8 => 'approver.role_id = ' . $roleId,
                9 => '(approver.role_id = 9 AND (f.form_type <> \'reimbursement\' OR a.sequence = 3))',
                7 => '((f.form_type = \'vehicle_request\' AND a.sequence = 4) OR (f.form_type IN (\'leave_application\', \'overtime_authorization\') AND a.sequence = 4))',
                default => '',
            };
            $canProcess = "CASE WHEN EXISTS (
                    SELECT 1 FROM approvals a
                    JOIN employees approver ON approver.id = a.approver_id
                    WHERE a.form_id = f.id AND a.status = 'pending'
                      AND NOT ({$roleId} = 9 AND f.form_type = 'reimbursement' AND f.submitted_by = {$userId})
                      AND (
                          (a.approver_id = ? AND (
                              f.form_type <> 'reimbursement'
                              OR (? = 5 AND a.sequence = 4 AND approver.role_id = 5)
                              OR (? = 9 AND a.sequence = 3 AND approver.role_id = 9)
                              OR ? NOT IN (5, 9)
                          ) AND NOT EXISTS (
                              SELECT 1 FROM approvals a3
                              WHERE a3.form_id = a.form_id AND a3.status = 'pending'
                                AND a3.sequence < a.sequence AND a3.approver_id <> a.approver_id
                          ))" . ($standInAction !== '' ? "
                          OR ({$standInAction} AND NOT EXISTS (
                              SELECT 1 FROM approvals a3
                              WHERE a3.form_id = a.form_id AND a3.status = 'pending'
                                AND a3.sequence < a.sequence
                          ))" : '') . "
                      )
                ) THEN 1 ELSE 0 END AS can_process";
            [$sql, $params] = self::query(
                $viewability . ', ' . $canProcess . ', f.id, f.form_type, f.status, f.submitted_by, e.full_name, f.created_at',
                [$userId, $roleId, $userId, $userId, $roleId, $roleId, $roleId, $roleId, $roleId, $roleId, $userId, $roleId, $roleId, $roleId, $userId, $roleId]
            );
            $stmt = db()->prepare($sql . ' ORDER BY f.created_at DESC');
            $stmt->execute($params);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        private static function query(string $select, array $params = []): array {
            $roleId = (int) ($params[1] ?? 0);
            $userId = (int) ($params[0] ?? 0);
            $sql = "SELECT {$select}
                    FROM forms f
                    JOIN employees e ON e.id = f.submitted_by
                    WHERE (
                          f.status IN ('submitted', 'immediatehead_approved', 'process_approved', 'department_reviewed', 'finance_reviewed')
                          OR ({$roleId} = 7 AND f.form_type = 'leave_application'
                              AND f.status = 'final_authorization'
                              AND LOWER(e.department) REGEXP '(^|[^[:alnum:]])admin(istration)?([^[:alnum:]]|$)')
                          OR ({$roleId} = 9 AND f.form_type IN ('reimbursement', 'leave_application', 'overtime_authorization')
                              AND f.status = 'final_authorization')
                      )
                      AND NOT ({$roleId} IN (5, 8) AND f.form_type = 'leave_application')
                      AND (
                          {$roleId} <> 9
                          OR f.form_type NOT IN ('advance_payment', 'request_for_payment')
                      )
                      AND (
                          f.form_type <> 'vehicle_request'
                          OR {$roleId} IN (6, 7)
                          OR f.submitted_by = {$userId}
                          OR EXISTS (
                              SELECT 1 FROM approvals vehicle_request_process_member
                              WHERE vehicle_request_process_member.form_id = f.id
                                AND vehicle_request_process_member.approver_id = {$userId}
                          )
                      )
                      AND (
                          {$roleId} <> 2
                          OR f.submitted_by = {$userId}
                          OR EXISTS (
                              SELECT 1 FROM approvals awaiting_approval
                              WHERE awaiting_approval.form_id = f.id
                                AND awaiting_approval.approver_id = {$userId}
                                AND awaiting_approval.status = 'pending'
                                AND (
                                    (f.form_type = 'vehicle_request'
                                        AND awaiting_approval.sequence = 3
                                        AND EXISTS (
                                            SELECT 1 FROM employees admin_evaluator
                                            WHERE admin_evaluator.id = {$userId}
                                              AND admin_evaluator.role_id = 2
                                              AND admin_evaluator.is_active = 1
                                              AND LOWER(admin_evaluator.department) REGEXP '(^|[^[:alnum:]])admin(istration)?([^[:alnum:]]|$)'
                                        ))
                                    OR (
                                        NOT (f.form_type = 'vehicle_request'
                                            AND awaiting_approval.sequence = 3)
                                        AND NOT EXISTS (
                                            SELECT 1 FROM approvals earlier_approval
                                            WHERE earlier_approval.form_id = f.id
                                              AND earlier_approval.status = 'pending'
                                              AND earlier_approval.sequence < awaiting_approval.sequence
                                        )
                                    )
                                )
                          )
                      )
                      AND (
                          {$roleId} IN (1, 5, 6, 7)
                          OR f.form_type <> 'advance_payment'
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
                      )
                      AND (
                          {$roleId} IN (1, 6)
                          OR f.form_type NOT IN ('leave_application', 'overtime_authorization')
                          OR LOWER(e.department) NOT REGEXP '(^|[^[:alnum:]])(management|human[[:space:]]+resources|hr|admin(istration)?)([^[:alnum:]]|$)'
                          OR f.submitted_by = ?
                          OR ? IN (1, 6, 9)
                          OR ({$roleId} = 7 AND f.form_type = 'leave_application'
                              AND LOWER(e.department) REGEXP '(^|[^[:alnum:]])admin(istration)?([^[:alnum:]]|$)')
                      )";
            $visibilityParams = [
                (int) ($params[0] ?? 0),
                (int) ($params[0] ?? 0),
                (int) ($params[0] ?? 0),
            ];
            array_splice($params, max(0, count($params) - 2), 0, $visibilityParams);

            return [$sql, $params];
        }
    }
