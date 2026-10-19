<?php
    namespace App\Services;

    class RejectedCountService {
        public static function forUser(int $userId, int $roleId): int {
            $sql = 'SELECT DISTINCT f.id, f.created_at
                    FROM forms f
                    JOIN employees e ON e.id = f.submitted_by
                    LEFT JOIN approvals a ON a.form_id = f.id
                    WHERE f.status = "rejected"';
            $params = [];

            if (!in_array($roleId, [1, 6, 7], true)) {
                $sql .= ' AND f.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            }

            if (!in_array($roleId, [1, 6, 7], true)) {
                if ($roleId === 8 || $roleId === 9) {
                    $sql .= ' AND (f.form_type = "advance_payment" OR (a.approver_id = ? OR f.submitted_by = ?
                        OR a.approver_id IN (SELECT id FROM employees WHERE role_id = ?)))';
                    $params = [$userId, $userId, $roleId];
                } elseif ($roleId === 5) {
                    $sql .= ' AND (f.form_type = "advance_payment" OR (a.approver_id = ? OR f.submitted_by = ?
                        OR a.approver_id IN (SELECT id FROM employees WHERE role_id = 5)))';
                    $params = [$userId, $userId];
                } elseif (in_array($roleId, [2, 4], true)) {
                    $sql .= ' AND (f.form_type = "advance_payment" OR (a.approver_id = ? OR f.submitted_by = ?))';
                    $params = [$userId, $userId];
                } else {
                    $sql .= ' AND (f.form_type = "advance_payment" OR f.submitted_by = ?)';
                    $params[] = $userId;
                }
            }

            if (!in_array($roleId, [1, 6, 7], true)) {
                $sql .= ' AND (f.form_type <> "advance_payment" OR f.submitted_by = ?
                    OR EXISTS (
                        SELECT 1 FROM approvals advance_payment_approval
                        WHERE advance_payment_approval.form_id = f.id
                          AND advance_payment_approval.approver_id = ?
                    )
                    OR EXISTS (
                        SELECT 1 FROM employees requester
                        WHERE requester.id = f.submitted_by
                          AND requester.supervisor_id_2 = ?
                    ))';
                array_push($params, $userId, $userId, $userId);
            }

            $sql .= ' ORDER BY f.created_at DESC';
            if ($roleId === 3) {
                $sql .= ' LIMIT 30';
            }

            $stmt = db()->prepare("SELECT COUNT(*) FROM ({$sql}) rejected_forms");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        }
    }
