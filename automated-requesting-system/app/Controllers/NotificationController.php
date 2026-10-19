<?php
    namespace App\Controllers;

    /**
     * NotificationController
     * 
     * Handles:
     *  GET /notifications/unread - JSON list for the panel (called by base.php)
     *  POST /notifications/{id}/read - mark one notification read
     *  POST /notifications/read-all - mark all read for current user
     * 
     * Static helper:
     *  NotificationController::create() - called by FormController after each
     *  pipeline action to insert a notification row.
     * 
     */
    
    class NotificationController {
        // ── Routes ──

        /** GET /notifications/unread - returns JSON for the bell panel */
        public function unread(): void {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            $userId = (int) $_SESSION['user_id'];
            $roleId = (int) $_SESSION['role_id'];
            $pendingCount = $this->refreshPendingCount($userId, $roleId);
            $overdueCount = $this->refreshOverdueCount($userId, $roleId);
            $postApprovalCount = 0;
            try {
                $postApprovalCount = \App\Services\PostApprovalService::countForUser($userId, $roleId);
            } catch (\Throwable $exception) {
                error_log('[NotificationController] Post approval KPI refresh failed.');
            }
            $rejectedCount = 0;
            try {
                $rejectedCount = \App\Services\RejectedCountService::forUser($userId, $roleId);
            } catch (\Throwable $exception) {
                error_log('[NotificationController] Rejected KPI refresh failed.');
            }
            $completedCount = 0;
            $approvedCount = 0;
            try {
                $completedCount = \App\Services\DashboardSortCountService::completedForUser($userId, $roleId);
                $approvedCount = \App\Services\DashboardSortCountService::approvedForUser($userId, $roleId);
            } catch (\Throwable $exception) {
                error_log('[NotificationController] Dashboard sort KPI refresh failed.');
            }

            if (($_GET['pending_only'] ?? '') === '1') {
                $awaitingApprovalCount = 0;
                try {
                    $awaitingApprovalCount = \App\Services\AwaitingApprovalService::countForUser($userId, $roleId);
                } catch (\Throwable $exception) {
                    error_log('[NotificationController] Awaiting approval KPI refresh failed.');
                }

                header('Content-Type: application/json');
                echo json_encode([
                    'pending_count' => $pendingCount,
                    'overdue_count' => $overdueCount,
                    'awaiting_approval_count' => $awaitingApprovalCount,
                    'post_approval_count' => $postApprovalCount,
                    'completed_count' => $completedCount,
                    'approved_count' => $approvedCount,
                    'rejected_count' => $rejectedCount,
                ]);
                exit;
            }

            $rows = $this->fetchForUser($userId, 10);
            $awaitingApprovalCount = 0;
            try {
                $awaitingApprovalCount = \App\Services\AwaitingApprovalService::countForUser($userId, $roleId);
            } catch (\Throwable $exception) {
                error_log('[NotificationController] Awaiting approval KPI refresh failed.');
            }

            header('Content-Type: application/json');
            echo json_encode([
                'unread_count' => array_reduce($rows, fn($c, $r) => $c + (int)!$r['is_read'], 0), 
                'pending_count' => $pendingCount,
                'overdue_count' => $overdueCount,
                'awaiting_approval_count' => $awaitingApprovalCount,
                'post_approval_count' => $postApprovalCount,
                'completed_count' => $completedCount,
                'approved_count' => $approvedCount,
                'rejected_count' => $rejectedCount,
                'items' => $rows,
            ]);
            exit;
        }

        /** POST /notifications/{id}/read */
        public function markRead(int $id): void {
            \App\Helpers\Csrf::verify();
            $userId = (int) $_SESSION['user_id'];

            db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }

        /** POST /notifications/read-all */
        public function markAllRead(): void {
            \App\Helpers\Csrf::verify();
            $userId = (int) $_SESSION['user_id'];

            db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$userId]);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }

        // ── Static helpers (called from FormController) ──
 
        /**
         * Insert a notification for one user.
         *
         * @param int $userId Recipient employee id
         * @param string $message Plain-text notification message
         * @param string $type 'info' | 'success' | 'warning' | 'danger'
         * @param int|null $formId Related form id (for the link)
         */

        public static function create(
            int $userId, 
            string $message, 
            string $type = 'info', 
            ?int $formId = null
        ): void {
            try {
                $link = $formId
                    ? url('forms/view/' . $formId)
                    : null;

                db()->prepare(
                    'INSERT INTO notifications (user_id, form_id, type, message, link)
                    VALUES (?, ?, ?, ?, ?)'
                )->execute([$userId, $formId, $type, $message, $link]);
            } catch (\Throwable $e) {
                // Never crash a user action due to a  notification failure
                error_log('[NotificationController] Insert failed: ' . $e->getMessage());
            }
        }

        // ── Private ──
        private function refreshPendingCount(int $userId, int $roleId): int {
            $pendingCount = (int) ($_SESSION["pending_count_{$userId}"] ?? 0);
            try {
                $pendingCount = \App\Services\PendingCountService::forUser($userId, $roleId);
                $_SESSION["pending_count_{$userId}"] = $pendingCount;
                $_SESSION["pending_count_ts_{$userId}"] = time();
            } catch (\Throwable $e) {}

            return $pendingCount;
        }

        private function refreshOverdueCount(int $userId, int $roleId): int {
            if (!in_array($roleId, [1, 2, 4, 5, 6, 7, 8, 9], true)) {
                return 0;
            }

            $sql = 'SELECT COUNT(*) FROM approvals a
                    JOIN forms f ON f.id = a.form_id
                    WHERE a.status = "pending"
                      AND DATEDIFF(NOW(), f.created_at) >= 3
                      AND NOT EXISTS (
                          SELECT 1 FROM approvals a3
                          WHERE a3.form_id = a.form_id
                            AND a3.status = "pending"
                            AND a3.sequence < a.sequence
                      )';
            $params = [];

            if (in_array($roleId, [1, 6, 7], true)) {
                $sql .= ' AND f.status NOT IN ("draft", "cancelled", "completed", "rejected")';
            } elseif ($roleId === 5) {
                $sql .= ' AND (
                    a.approver_id = ?
                    OR a.approver_id IN (SELECT id FROM employees WHERE role_id = 5)
                    OR (
                        f.form_type = "liquidation"
                        AND a.sequence = 3
                        AND a.approver_id IN (SELECT id FROM employees WHERE role_id = 9)
                        AND NOT EXISTS (
                            SELECT 1 FROM approvals a5
                            JOIN employees e5 ON e5.id = a5.approver_id
                            WHERE a5.form_id = f.id AND a5.sequence = 3 AND e5.role_id = 5
                        )
                    )
                ) AND (
                    f.form_type <> "reimbursement"
                    OR (a.sequence = 4 AND a.approver_id IN (SELECT id FROM employees WHERE role_id = 5))
                ) AND f.status NOT IN ("draft", "completed", "rejected", "cancelled")';
                $params = [$userId];
            } elseif (in_array($roleId, [ 6, 8, 9 ], true)) {
                $sql .= ' AND (a.approver_id = ?
                    OR a.approver_id IN (SELECT id FROM employees WHERE role_id = ?))
                    AND f.status NOT IN ("draft", "completed", "rejected", "cancelled")';
                $params = [$userId, $roleId];
            } elseif ($roleId === 4) {
                $sql .= ' AND (a.approver_id = ? OR a.sequence = 2)
                    AND f.status NOT IN ("draft", "completed", "rejected", "cancelled")';
                $params = [$userId];
            } elseif ($roleId === 2) {
                $sql .= ' AND a.approver_id = ?
                    AND f.status NOT IN ("draft", "completed", "rejected", "cancelled")';
                $params = [$userId];
            }

            try {
                $stmt = db()->prepare($sql);
                $stmt->execute($params);
                return (int) $stmt->fetchColumn();
            } catch (\Throwable $e) {
                error_log('[NotificationController] Overdue KPI refresh failed: ' . $e->getMessage());
                return 0;
            }
        }

        private function fetchForUser(int $userId, int $limit = 10): array {
            $stmt = db()->prepare(
                'SELECT id, form_id, type, message, link, is_read, created_at
                FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?'
            );
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
    }
