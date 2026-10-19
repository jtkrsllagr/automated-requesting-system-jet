<?php

namespace App\Controllers;

    /**
     *  ChatController (improved)
     *
     */

    class ChatController {

        // ── Helpers ── //
        public function me(): int {
            return (int) ($_SESSION['user_id'] ?? 0);
        }

        private function json(mixed $data, int $status = 200): void {
            // Clean all output buffer levels so no HTML warnings/notices
            // from require chains pollute the JSON response body.
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            // Prevent any downstream code from appending to this response.
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        private function avatarUrl(?string $avatar): string {
            if (!$avatar || $avatar === 'default.png') return url('images/default-avatar.png');
            if (str_starts_with($avatar, 'http')) return $avatar;
            $avatar = ltrim($avatar, '/');
            if (str_starts_with($avatar, 'uploads/avatars/')) return url($avatar);
            return url('uploads/avatars/' . $avatar);
        }

        /** Update last_seen_at for the current user */
        private function updatePresence(int $me): void {
           try {
            db()->prepare(
                'UPDATE employees SET last_seen_at = NOW() WHERE id = ?'
            )->execute([$me]);
           } catch (\Throwable $e) {
            // No columns yet.
           }
        }

        // ── Page ── //

        /** GET /chat */
        public function index(): void {
            $me = $this->me();
            if (!$me) { header('Location: ' . url('login')); exit; }

            $this->updatePresence($me);

            $pageTitle = 'Messaging';
            $breadcrumbs = [[ 'label' => 'Messaging' ]];
            define('BASE_LOADED', true);
            ob_start();
            require __DIR__ . '/../../views/chat/index.php';
            $content = ob_get_clean();
            require __DIR__ . '/../../views/layouts/base.php';
        }

        // ── API endpoints ── //

        /** GET /chat/users */
        public function users(): void {
            $me = $this->me();
            if (!$me) { $this->json([], 401); return; }
            $this->updatePresence($me);
            try {
                $this->json($this->fetchUsers($me));
            } catch (\Throwable $e) {
                $this->json([]);
            }
        }
        /**
         * GET /chat/messages?with={id}&limit={n}&before={id}
         *
         * Returns { messages: [...], has_more: bool }
         * Older messages: pass ?before=<oldest_id_you_have>
         */
        public function messages(): void {
            $me = $this->me();
            $with = (int) ($_GET['with'] ?? 0);
            $limit = min((int) ($_GET['limit'] ?? 30), 100);
            $before = (int) ($_GET['before'] ?? 0);

            if (!$me) { $this->json(['messages' => [], 'has_more' => false], 401); return; }
            if (!$with) { $this->json(['messages' => [], 'has_more' => false], 400); return; }

            $this->updatePresence($me);

            $wherePeer = '((cm.sender_id = ? AND cm.receiver_id = ?) OR (cm.sender_id = ? AND cm.receiver_id = ?))';
            $beforeClause = $before ? 'AND cm.id < ?' : '';

            $sql = "SELECT cm.id, cm.sender_id, cm.receiver_id, cm.message,
                        cm.message_type, cm.attachment_url, cm.form_id,
                        cm.sent_at, cm.is_read,
                           e.full_name AS sender_name,
                           e.avatar AS sender_avatar,
                           f.form_type AS shared_form_type,
                           f.status AS shared_form_status,
                           f.submitted_by AS shared_form_submitted_by
                    FROM chat_messages cm
                    JOIN employees e ON e.id = cm.sender_id
                    LEFT JOIN forms f ON f.id = cm.form_id
                    WHERE $wherePeer $beforeClause
                    ORDER BY cm.sent_at DESC
                    LIMIT " . ($limit + 1);  // fetch one extra to detect has_more

            $params = [$me, $with, $with, $me];
            if ($before) $params[] = $before;

            try {
                $stmt = db()->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
            } catch (\Throwable $e) {
                $this->json(['messages' => [], 'has_more' => false, 'error' => 'DB error'], 500);
                return;
            }

            $hasMore = count($rows) > $limit;
            if ($hasMore) array_pop($rows);

            $rows = array_reverse($rows);

            try {
                $ids = array_column($rows, 'id');
                $reactions = $this->getReactions(array_map('intval', $ids));
            } catch (\Throwable $e) {
                $reactions = [];
            }
            foreach ($rows as &$r) {
                $r['sender_avatar'] = $this->avatarUrl($r['sender_avatar']);
                $r['is_mine'] = ((int)$r['sender_id'] === $me);
                $r['is_read'] = (bool) $r['is_read'];
                $r['reactions'] = $reactions[(int)$r['id']] ?? [];

                if ($r['message_type'] === 'form_share' && $r['form_id']) {
                    $r['form_id'] = (int) $r['form_id'];
                    $r['shared_form_url'] = url('forms/view/' . $r['form_id']);
                    $r['shared_form_label'] = $r['shared_form_type']
                        ? \App\Helpers\FormLabels::get($r['shared_form_type'])
                        : 'Form';
                }
            }
            unset($r);

            $this->json(['messages' => $rows, 'has_more' => $hasMore]);

        }

        /** POST /chat/send */
        public function send(): void {
            \App\Helpers\Csrf::verify();

            $me = $this->me();
            $receiverId = (int) ($_POST['receiver_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            
            if (!$receiverId || $message === '') {
                $this->json(['error' => 'Missing fields'], 400);
                return;
            }

            try {
                $blocked = db()->prepare(
                    'SELECT 1 FROM chat_blocked_users
                    WHERE (blocker_id = ? AND blocked_id = ?)
                    OR (blocker_id = ? AND blocked_id = ?)
                    LIMIT 1'
                );
                $blocked->execute([$me, $receiverId, $receiverId, $me]);
                if ($blocked->fetch()) {
                    $this->json(['error' => 'Blocked'], 403);
                    return;
                }

                $type = in_array($_POST['message_type'] ?? '', ['text', 'sticker'], true)
                    ? $_POST['message_type']
                    : 'text';

                $stmt = db()->prepare(
                    'INSERT INTO chat_messages (sender_id, receiver_id, message, message_type)
                    VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$me, $receiverId, $message, $type]);

                $id = (int) db()->lastInsertId();

                $this->clearTyping($me, $receiverId);

                $this->json(['id' => $id, 'sent_at' => gmdate('Y-m-d H:i:s')]);
            } catch (\Throwable $e) {
                $this->json(['error' => $e->getMessage()], 500);
            }
        }

        /** GET /chat/unread */
        public function unread(): void {
            $me = $this->me();
            if (!$me) { $this->json(['unread' => 0], 401); return; }


            try { 
                $stmt = db()->prepare(
                    'SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0'
                );
                $stmt->execute([$me]);
                $this->json(['unread' => (int) $stmt->fetchColumn()]);
            } catch (\Throwable $e) {
                $this->json(['unread' => 0]);
            }
        }

        /** GET /chat/poll?with={id}&after={id} */
        public function poll(): void {
            $me = $this->me();
            if (!$me) { $this->json([], 401); return; }

            $with = (int) ($_GET['with'] ?? 0);
            $afterId = (int) ($_GET['after'] ?? 0);
            if (!$with) { $this->json([], 400); return; }

            $this->updatePresence($me);

            try {
                // Mark messages from peer as read while the conversation is open
                db()->prepare(
                    'UPDATE chat_messages SET is_read = 1
                    WHERE receiver_id = ? AND sender_id = ? AND is_read = 0'
                )->execute([$me, $with]);

                $stmt = db()->prepare(
                    'SELECT cm.id, cm.sender_id, cm.receiver_id, cm.message,
                            cm.message_type, cm.attachment_url,
                            cm.sent_at, cm.is_read,
                            e.full_name AS sender_name,
                            e.avatar AS sender_avatar
                    FROM chat_messages cm
                    JOIN employees e ON e.id = cm.sender_id
                    WHERE ((cm.sender_id = ? AND cm.receiver_id = ?)
                    OR (cm.sender_id = ? AND cm.receiver_id = ?))
                    AND cm.id > ?
                    ORDER BY cm.sent_at ASC'
                );
                $stmt->execute([ $me, $with, $with, $me, $afterId ]);
                $rows = $stmt->fetchAll();

                $ids = array_column($rows, 'id');
                $reactions = $this->getReactions(array_map('intval', $ids));

                foreach ($rows as &$r) {
                    $r['sender_avatar'] = $this->avatarUrl($r['sender_avatar']);
                    $r['is_mine'] = ((int)$r['sender_id'] === $me);
                    $r['is_read'] = (bool) $r['is_read'];
                    $r['reactions'] = $reactions[(int)$r['id']] ?? [];
                }
                unset($r);

                // Only return messages the client doesn't already have.
                // Filter out own messages that were appended optimistically on send
                // — the client tracks lastMessageId so they won't be re-appended,
                // but returning them wastes bandwidth.
                $this->json(array_values($rows));
            } catch (\Throwable $e) {
                $this->json([]);
            }
        }

        /**
         * POST /chat/typing  { receiver_id, typing: 1|0 }
         * GET  /chat/typing?with={id}  → { typing: bool }
         *
         * Stores a lightweight flag in chat_typing (or a session/cache).
         * Falls back to a minimal DB table if no cache is available.
         */
        public function typing(): void {
            $me = $this->me();
            if (!$me) { $this->json(['typing' => false], 401); return; }

            try{ 
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                \App\Helpers\Csrf::verify();
                $receiver = (int) ($_POST['receiver_id'] ?? 0);
                $typing = (int) ($_POST['typing']      ?? 0);
                if (!$receiver) { $this->json(['ok' => false], 400); return; }

                if ($typing) {
                    db()->prepare(
                        'INSERT INTO chat_typing (sender_id, receiver_id, updated_at)
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE updated_at = NOW()'
                    )->execute([$me, $receiver]);
                } else {
                    $this->clearTyping($me, $receiver);
                }
                $this->json(['ok' => true]);

            } else {
                // GET
                $with = (int) ($_GET['with'] ?? 0);
                if (!$with) { $this->json(['typing' => false]); return; }

                $stmt = db()->prepare(
                    'SELECT 1 FROM chat_typing
                    WHERE sender_id = ? AND receiver_id = ?
                    AND updated_at > DATE_SUB(NOW(), INTERVAL 4 SECOND)'
                );
                $stmt->execute([$with, $me]);
                $this->json(['typing' => (bool) $stmt->fetch()]);
            }
            } catch (\Throwable $e) {
                $this->json(['typing' => false]);
            }
        }

        private function clearTyping(int $sender, int $receiver): void {
            db()->prepare(
                'DELETE FROM chat_typing WHERE sender_id = ? AND receiver_id = ?'
            )->execute([$sender, $receiver]);
        }

        /** POST /chat/react */
        public function react(): void {
            \App\Helpers\Csrf::verify();
            $me = $this->me();
            $messageId = (int) ($_POST['message_id'] ?? 0);
            $emoji = trim($_POST['emoji'] ?? '');
            $allowed = ['👍','✅','❤️','😂','🙏'];

            if (!$messageId || !in_array($emoji, $allowed, true)) {
                $this->json(['error' => 'Invalid'], 400);
                return;
            }

            // Verify message is in a conversation the user belongs to
            $msg = db()->prepare('SELECT sender_id, receiver_id FROM chat_messages WHERE id = ?');
            $msg->execute([$messageId]);
            $row = $msg->fetch();
            if (!$row || !in_array($me, [(int)$row['sender_id'], (int)$row['receiver_id']])) {
                $this->json(['error' => 'Forbidden'], 403);
                return;
            }

            // Toggle: same roll = remove, different = replace
            $existing = db()->prepare('SELECT emoji FROM chat_reactions WHERE message_id = ? AND user_id = ?');
            $existing->execute([$messageId, $me]);
            $cur = $existing->fetchColumn();

            if ($cur === $emoji) {
                db()->prepare('DELETE FROM chat_reactions WHERE message_id = ? AND user_id = ?')->execute([$messageId, $me]);
            } else {
                db()->prepare(
                    'INSERT INTO chat_reactions (message_id, user_id, emoji) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), created_at = NOW()'
                )->execute([$messageId, $me, $emoji]);
            }

            $this->json(['ok' => true, 'reactions' => $this->getReactions([$messageId])[$messageId] ?? []]);
        }

        /** POST /chat/delete */
        public function delete(): void {
            \App\Helpers\Csrf::verify();
            $me = $this->me();
            $messageId = (int) ($_POST['message_id'] ?? 0);
            
            if (!$me || !$messageId) { 
                $this->json(['error' => 'Invalid'], 400); 
                return; 
            }

            $stmt = db()->prepare('SELECT sender_id FROM chat_messages WHERE id = ?');
            $stmt->execute([$messageId]);
            $row = $stmt->fetch();

            if (!$row || (int)$row['sender_id'] !== $me) {
                $this->json(['error' => 'Forbidden'], 403); 
                return;
            }

            db()->prepare('DELETE FROM chat_messages WHERE id = ?')->execute([$messageId]);
            $this->json(['ok' => true]);
        }

        /** POST /chat/mark-read */
        public function markRead(): void {
            \App\Helpers\Csrf::verify();
            $me = $this->me();
            $with = (int) ($_POST['with'] ?? 0);
            if (!$me || !$with) { $this->json(['ok' => false], 400); return; }
            try {
                db()->prepare('UPDATE chat_messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0')
                    ->execute([$me, $with]);
                $this->json(['ok' => true]);
            } catch (\Throwable $e) {
                $this->json(['ok' => false]);
            }
        }

        /** GET /chat/block-status?with={userId} */
        public function blockStatus(): void {
            $me = $this->me();
            $with = (int) ($_GET['with'] ?? 0);
            if (!$me) { $this->json(['blocked' => false], 401); return; }
            if (!$with) { $this->json(['blocked' => false], 400); return; }

            try {
                $stmt = db()->prepare(
                    'SELECT 1 FROM chat_blocked_users
                     WHERE (blocker_id = ? AND blocked_id = ?)
                        OR (blocker_id = ? AND blocked_id = ?)
                     LIMIT 1'
                );
                $stmt->execute([$me, $with, $with, $me]);
                $this->json(['blocked' => (bool) $stmt->fetch()]);
            } catch (\Throwable $e) {
                $this->json(['blocked' => false]);
            }
        }

        /** POST /chat/block */
        public function block(): void {
            \App\Helpers\Csrf::verify();

            $me = $this->me();
            $target = (int) ($_POST['user_id'] ?? 0);
            if (!$target || $target === $me) { $this->json(['error' => 'Invalid'], 400); return; }

            db()->prepare(
                'INSERT IGNORE INTO chat_blocked_users (blocker_id, blocked_id) VALUES (?, ?)'
            )->execute([$me, $target]);

            $this->json(['ok' => true]);
        }

        /** POST /chat/unblock */
        public function unblock(): void {
            \App\Helpers\Csrf::verify();

            $me = $this->me();
            $target = (int) ($_POST['user_id'] ?? 0);
            if (!$target || $target === $me) { $this->json(['error' => 'Invalid'], 400); return; }

            db()->prepare(
                'DELETE FROM chat_blocked_users WHERE blocker_id = ? AND blocked_id = ?'
            )->execute([$me, $target]);

            $this->json(['ok' => true]);
        }

        // ── Internal ── //

        private function fetchUsers(int $me): array {
            // Step 1: Always load employees — this query has no chat dependency.
            $stmt = db()->prepare(
                'SELECT e.id, e.full_name, e.avatar, e.department, e.last_seen_at
                 FROM employees e
                 WHERE e.id != ? AND e.is_active = 1
                 ORDER BY e.full_name ASC'
            );
            $stmt->execute([$me]);
            $rows = $stmt->fetchAll();

            // Step 2: Enrich with chat data (unread count, last message).
            // Wrapped in try/catch so employees still show if chat tables
            // haven't been migrated yet.
            try {
                $chatStmt = db()->prepare(
                    'SELECT * FROM (
                        SELECT e.id,
                            (SELECT COUNT(*) FROM chat_messages cm
                             WHERE cm.sender_id = e.id AND cm.receiver_id = ?
                               AND cm.is_read = 0) AS unread_count,
                            (SELECT cm2.message FROM chat_messages cm2
                             WHERE (cm2.sender_id = e.id AND cm2.receiver_id = ?)
                                OR (cm2.sender_id = ? AND cm2.receiver_id = e.id)
                             ORDER BY cm2.sent_at DESC LIMIT 1) AS last_message,
                            (SELECT cm2b.message_type FROM chat_messages cm2b
                             WHERE (cm2b.sender_id = e.id AND cm2b.receiver_id = ?)
                                OR (cm2b.sender_id = ? AND cm2b.receiver_id = e.id)
                             ORDER BY cm2b.sent_at DESC LIMIT 1) AS last_message_type,
                            (SELECT cm3.sent_at FROM chat_messages cm3
                             WHERE (cm3.sender_id = e.id AND cm3.receiver_id = ?)
                                OR (cm3.sender_id = ? AND cm3.receiver_id = e.id)
                             ORDER BY cm3.sent_at DESC LIMIT 1) AS last_message_at
                        FROM employees e
                        WHERE e.id != ? AND e.is_active = 1
                    ) AS t'
                );
                $chatStmt->execute([$me, $me, $me, $me, $me, $me, $me, $me]);
                $chatData = [];
                foreach ($chatStmt->fetchAll() as $c) {
                    $chatData[(int)$c['id']] = $c;
                }

                foreach ($rows as &$r) {
                    $c = $chatData[(int)$r['id']] ?? [];
                    $r['unread_count'] = (int) ($c['unread_count'] ?? 0);
                    $r['last_message_at'] = $c['last_message_at'] ?? null;

                    $lastType = $c['last_message_type'] ?? null;
                    if ($lastType === 'form_share') {
                        $r['last_message'] = '📄 Shared a form';
                    } elseif ($lastType === 'attachment') {
                        $r['last_message'] = '📎 Sent an attachment';
                    } else {
                        $r['last_message'] = $c['last_message'] ?? null;
                    }
                }
                unset($r);

                // Sort: unread first, then most recent message, then name
                usort($rows, function ($a, $b) {
                    $aUnread = $a['unread_count'] > 0 ? 1 : 0;
                    $bUnread = $b['unread_count'] > 0 ? 1 : 0;
                    if ($aUnread !== $bUnread) return $bUnread - $aUnread;
                    if ($a['last_message_at'] !== $b['last_message_at']) {
                        return ($b['last_message_at'] ?? '') <=> ($a['last_message_at'] ?? '');
                    }
                    return strcmp($a['full_name'], $b['full_name']);
                });

            } catch (\Throwable $e) {
                // Chat tables not migrated yet — employees still show,
                // just without unread counts or last message previews.
                foreach ($rows as &$r) {
                    $r['unread_count'] = 0;
                    $r['last_message'] = null;
                    $r['last_message_at'] = null;
                }
                unset($r);
            }

            foreach ($rows as &$r) {
                $r['avatar'] = $this->avatarUrl($r['avatar']);
            }
            unset($r);

            return $rows;
        }

        /** Fetch aggregated reactions for an array of message IDs */
        private function getReactions(array $ids): array {
            if (!$ids) return [];
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = db()->prepare(
                "SELECT message_id, emoji, COUNT(*) AS cnt FROM chat_reactions WHERE message_id IN ($placeholders) GROUP BY message_id, emoji"
            );
            $stmt->execute(array_values($ids));
            $out = [];
            foreach ($stmt->fetchAll() as $r) {
                $out[(int)$r['message_id']][] = ['emoji' => $r['emoji'], 'count' => (int)$r['cnt']];
            }

            return $out;
        }

        /** POST /chat/upload */
        public function upload(): void {
            \App\Helpers\Csrf::verify();
            $me = $this->me();
            if (!$me) { $this->json(['error' => 'Unauthorized'], 401); return; }

            $receiverId = (int) ($_POST['receiver_id'] ?? 0);
            if (!$receiverId) { $this->json(['error' => 'Missing receiver'], 400); return; }

            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $this->json(['error' => 'Upload failed'], 400); return;
            }

            $maxBytes = 5 * 1024 * 1024; // 5 MB
            if ($file['size'] > $maxBytes) { $this->json(['error' => 'File too large (max 5 MB)'], 400); return; }

            $allowed = ['image/jpeg','image/png','image/gif','image/webp',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            $mime = mime_content_type($file['tmp_name']);
            if (!in_array($mime, $allowed, true)) { $this->json(['error' => 'File type not allowed'], 400); return; }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $name = bin2hex(random_bytes(12)) . '.' . strtolower($ext);
            $dir = __DIR__ . '/../../public/uploads/chat/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);

            if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
                $this->json(['error' => 'Could not save file'], 500); return;
            }

            $url = url('uploads/chat/' . $name);

            try {
                $stmt = db()->prepare(
                    'INSERT INTO chat_messages (sender_id, receiver_id, message, message_type, attachment_url)
                    VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$me, $receiverId, $file['name'], 'attachment', $url]);
                $id = (int) db()->lastInsertId();
                $this->json(['id' => $id, 'url' => $url, 'filename' => $file['name'], 'sent_at' => gmdate('Y-m-d H:i:s')]);
            } catch (\Throwable $e) {
                $this->json(['error' => $e->getMessage()], 500);
            }
        }

        /** POST /chat/share-form */
        public function shareForm(): void {
            \App\Helpers\Csrf::verify();

            $me = $this->me();
            if (!$me) { $this->json(['error' => 'Unauthorized'], 401); return; }

            $receiverId = (int) ($_POST['receiver_id'] ?? 0);
            $formId = (int) ($_POST['form_id'] ?? 0);
            $note = trim($_POST['message'] ?? '');

            if (!$receiverId || !$formId) {
                $this->json(['error' => 'Missing fields'], 400);
                return;
            }
            if ($receiverId === $me) {
                $this->json(['error' => 'Cannot share a form with yourself'], 400);
                return;
            }

            try {
                $blocked = db()->prepare(
                    'SELECT 1 FROM chat_blocked_users
                    WHERE (blocker_id = ? AND blocked_id = ?)
                    OR (blocker_id = ? AND blocked_id = ?)
                    LIMIT 1'
                );
                $blocked->execute([$me, $receiverId, $receiverId, $me]);
                if ($blocked->fetch()) {
                    $this->json(['error' => 'Blocked'], 403);
                    return;
                }

                if (!$this->canAccessForm($formId, $me)) {
                    $this->json(['error' => 'You do not have permission to share this form'], 403);
                    return;
                }

                $recipientExists = db()->prepare('SELECT 1 FROM employees WHERE id = ? AND is_active = 1');
                $recipientExists->execute([$receiverId]);
                if (!$recipientExists->fetch()) {
                    $this->json(['error' => 'Recipient not found'], 404);
                    return;
                }

                $stmt = db()->prepare(
                    'INSERT INTO chat_messages (sender_id, receiver_id, message, message_type, form_id)
                    VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$me, $receiverId, $note, 'form_share', $formId]);

                $id = (int) db()->lastInsertId();
                $this->clearTyping($me, $receiverId);

                $this->json(['id' => $id, 'sent_at' => gmdate('Y-m-d H:i:s')]);
            } catch (\Throwable $e) {
                $this->json(['error' => $e->getMessage()], 500);
            }
        }

        /** Mirrors FormController::findForm() access rules: admin, submitter, or any assigned approver may share/view. */
        private function canAccessForm(int $formId, int $userId): bool {
            $stmt = db()->prepare('SELECT submitted_by FROM forms WHERE id = ?');
            $stmt->execute([$formId]);
            $form = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$form) return false;

            $roleId = (int) ($_SESSION['role_id'] ?? 0);
            if ($roleId === 1) return true;
            if ((int) $form['submitted_by'] === $userId) return true;

            $assigned = db()->prepare('SELECT 1 FROM approvals WHERE form_id = ? AND approver_id = ? LIMIT 1');
            $assigned->execute([$formId, $userId]);
            return (bool) $assigned->fetch();
        }


    }