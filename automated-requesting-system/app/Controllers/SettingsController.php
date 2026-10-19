<?php
    class SettingsController {

        // ── GET /settings ── //
        public function index(): void {
            \App\Middleware\AuthMiddleware::require();

            $userId = (int)($_SESSION['user_id'] ?? 0);
            $isSysAdmin = ($_SESSION['role_id'] ?? 0) == 1;

            // Global settings (admin-managed)
            $globalSettings = $this->getAllSettings();

            // Per-user overrides for appearance + notifications
            $userSettings = $this->getUserSettings($userId);

            // Merge: user values take precedence for their own keys;
            // everything else falls back to global defaults.
            $settings = array_merge($globalSettings, $userSettings);

            define('BASE_LOADED', true);
            ob_start();
            require __DIR__ . '/../../views/settings/index.php';
            $content = ob_get_clean();
            $pageTitle = 'Settings';
            require __DIR__ . '/../../views/layouts/base.php';
        }

        // ── POST /settings/general ── //
        // SysAdmin only
        public function updateGeneral(): void {
            \App\Middleware\RoleMiddleware::requireRole(1);
            \App\Helpers\Csrf::verify();

            $fields = [
                'app_name' => trim($_POST['app_name'] ?? ''),
                'app_url' => rtrim($_POST['app_url'] ?? ''),
                'app_timezone' => trim($_POST['app_timezone'] ?? 'Asia/Manila'),
                'app_env' => trim($_POST['app_env'] ?? 'local'),
                'items_per_page' => (int)($_POST['items_per_page'] ?? 20),
                'session_lifetime' => (int)($_POST['session_lifetime'] ?? 120),
            ];

            foreach ($fields as $key => $value) {
                $this->upsertGlobalSetting($key, (string)$value);
            }

            $_SESSION['success'] = 'General settings saved successfully.';
            header('Location: ' . url('settings'));
            exit;
        }

        // ── POST /settings/mail ── //
        // SysAdmin only
        public function updateMail(): void {
            \App\Middleware\RoleMiddleware::requireRole(1);
            \App\Helpers\Csrf::verify();

            $fields = [
                'mail_host' => trim($_POST['mail_host'] ?? ''),
                'mail_port' => (int)($_POST['mail_port'] ?? 587),
                'mail_username' => trim($_POST['mail_username'] ?? ''),
                'mail_from_address' => trim($_POST['mail_from_address'] ?? ''),
                'mail_from_name' => trim($_POST['mail_from_name'] ?? ''),
                'mail_encryption' => trim($_POST['mail_encryption'] ?? 'tls'),
            ];

            if (!empty($_POST['mail_password'])) {
                $fields['mail_password'] = $_POST['mail_password'];
            }

            foreach ($fields as $key => $value) {
                $this->upsertGlobalSetting($key, (string)$value);
            }

            $_SESSION['success'] = 'Mail settings saved successfully.';
            header('Location: ' . url('settings'));
            exit;
        }

        // ── POST /settings/appearance ── //
        // All authenticated users — saved to user_settings (per-account)
        public function updateAppearance(): void {
            \App\Middleware\AuthMiddleware::require();
            \App\Helpers\Csrf::verify();

            $userId = (int)($_SESSION['user_id'] ?? 0);

            $allowed_colors = ['blue', 'purple', 'green', 'orange'];
            $color = trim($_POST['theme_color'] ?? 'blue');

            $fields = [
                'theme_color' => in_array($color, $allowed_colors, true) ? $color : 'blue',
                'theme_mode' => trim($_POST['theme_mode'] ?? 'light') === 'dark' ? 'dark' : 'light',
                'sidebar_collapsed' => isset($_POST['sidebar_collapsed']) ? '1' : '0',
            ];

            foreach ($fields as $key => $value) {
                $this->upsertUserSetting($userId, $key, $value);
            }

            $_SESSION['success'] = 'Appearance settings saved.';
            header('Location: ' . url('settings#appearance'));
            exit;
        }

        // ── POST /settings/notifications ── //
        // All authenticated users — saved to user_settings (per-account)
        public function updateNotifications(): void {
            \App\Middleware\AuthMiddleware::require();
            \App\Helpers\Csrf::verify();

            $userId = (int)($_SESSION['user_id'] ?? 0);

            $fields = [
                'notify_on_submit' => isset($_POST['notify_on_submit'])     ? '1' : '0',
                'notify_on_approval' => isset($_POST['notify_on_approval'])   ? '1' : '0',
                'notify_on_rejection' => isset($_POST['notify_on_rejection'])  ? '1' : '0',
                'notify_on_completion' => isset($_POST['notify_on_completion']) ? '1' : '0',
            ];

            foreach ($fields as $key => $value) {
                $this->upsertUserSetting($userId, $key, $value);
            }

            $_SESSION['success'] = 'Notification preferences saved.';
            header('Location: ' . url('settings#notifications'));
            exit;
        }

        // ── POST /settings/storage ── //
        // SysAdmin only
        public function updateStorage(): void {
            \App\Middleware\RoleMiddleware::requireRole(1);
            \App\Helpers\Csrf::verify();

            $uploadPath = trim($_POST['upload_path'] ?? 'public/uploads');
            $uploadPath = ltrim(str_replace(['..', '//'], '', $uploadPath), '/');

            $rawTypes = trim($_POST['allowed_file_types'] ?? 'pdf,jpg,png,docx');
            $types = implode(',', array_filter(array_map(
                fn($t) => preg_replace('/[^a-z0-9]/', '', strtolower(trim($t))),
                explode(',', $rawTypes)
            )));

            $fields = [
                'upload_path' => $uploadPath,
                'max_file_size_mb' => max(1, min(100, (int)($_POST['max_file_size_mb'] ?? 10))),
                'allowed_file_types' => $types ?: 'pdf,jpg,png,docx',
            ];

            foreach ($fields as $key => $value) {
                $this->upsertGlobalSetting($key, (string)$value);
            }

            $_SESSION['success'] = 'Storage settings saved.';
            header('Location: ' . url('settings#storage'));
            exit;
        }

        // ── Private helpers ── //

        /** All rows from the global settings table (key => value). */
        private function getAllSettings(): array {
            try {
                $rows = db()->query('SELECT `key`, `value` FROM settings')->fetchAll(\PDO::FETCH_ASSOC);
                return array_column($rows, 'value', 'key');
            } catch (\Throwable $e) {
                return [];
            }
        }

        /** Per-user rows from user_settings (key => value) for one user. */
        private function getUserSettings(int $userId): array {
            if ($userId === 0) return [];
            try {
                $stmt = db()->prepare(
                    'SELECT `key`, `value` FROM user_settings WHERE user_id = ?'
                );
                $stmt->execute([$userId]);
                return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'value', 'key');
            } catch (\Throwable $e) {
                // Table may not exist yet (migration not run)
                return [];
            }
        }

        /** Write/update a row in the global settings table. */
        private function upsertGlobalSetting(string $key, string $value): void {
            $stmt = db()->prepare(
                'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$key, $value]);
        }

        /** Write/update a row in user_settings for the given user. */
        private function upsertUserSetting(int $userId, string $key, string $value): void {
            $stmt = db()->prepare(
                'INSERT INTO user_settings (user_id, `key`, `value`) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$userId, $key, $value]);
        }
    }