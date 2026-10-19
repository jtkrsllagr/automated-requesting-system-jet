<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    class AuthController {

        public function login(): void {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

            \App\Helpers\Csrf::verify();

            $ip  = $_SERVER['REMOTE_ADDR'];
            $key = 'login_attempts_' . hash('sha256', $ip);

            if (!isset($_SESSION[$key])) {
                $_SESSION[$key] = ['count' => 0, 'time' => time()];
            }

            if (time() - $_SESSION[$key]['time'] > 900) {
                $_SESSION[$key] = ['count' => 0, 'time' => time()];
            }

            if ($_SESSION[$key]['count'] >= 5) {
                $_SESSION['login_error'] = 'Too many login attempts. Try again in 15 minutes.';
                header('Location: ' . url('login'));
                exit;
            }

            $input = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            // inspect input if email
            $isEmail = filter_var($input, FILTER_VALIDATE_EMAIL);

            $stmt = db()->prepare(
                $isEmail
                    ? 'SELECT id, full_name, password_hash, role_id, department, is_active, avatar FROM employees WHERE email = ?'
                    : 'SELECT id, full_name, password_hash, role_id, department, is_active, avatar FROM employees WHERE username  = ?'
            );

            $stmt->execute([$input]);
            $employee = $stmt->fetch();

            if (!$employee || !$employee['is_active'] || !password_verify($password, $employee['password_hash'])) {
                $_SESSION[$key]['count']++;
                $_SESSION['login_error'] = 'Invalid credentials or account inactive.';
                $_SESSION['old_email'] = htmlspecialchars($input);
                header('Location: ' . url('login'));
                exit;
            }

            unset($_SESSION[$key]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $employee['id'];
            $_SESSION['user_name'] = $employee['full_name'];
            $_SESSION['role_id'] = $employee['role_id'];
            $_SESSION['department'] = $employee['department'] ?? '';
            $_SESSION['avatar'] = $employee['avatar'] ?? '';
            unset($_SESSION['csrf_token']); // Rotate CSRF token on successful login

            header('Location: ' . url('dashboard'));
            exit;
        }

        public function logout(): void {
            session_destroy();
            header('Location: ' . url('login'));
            exit;
        }

        public function forgotPassword(): void {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

            \App\Helpers\Csrf::verify();
            
            $email = trim($_POST['email'] ?? '');

            $stmt = db()->prepare('SELECT id FROM employees WHERE email = ? AND is_active = 1');
            $stmt->execute([$email]);
            $employee = $stmt->fetch();

            // Always show same message to prevent email enumeration
            $_SESSION['status'] = 'If that email exists, a reset link has been sent.';

            if ($employee) {
                $token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

                // Invalidate old tokens
                db()->prepare('DELETE FROM password_reset_tokens WHERE employee_id = ?')
                ->execute([$employee['id']]);

                db()->prepare(
                    'INSERT INTO password_reset_tokens (employee_id, token, expires_at) VALUES (?, ?, ?)'
                )->execute([$employee['id'], $token, $expiresAt]);

                $resetLink = $_ENV['APP_URL'] . '/reset-password?token=' . $token;

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = $_ENV['MAIL_HOST'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $_ENV['MAIL_USERNAME'];
                    $mail->Password = $_ENV['MAIL_PASSWORD'];
                    $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
                    $mail->Port = $_ENV['MAIL_PORT'] ?? 587;

                    $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME'] ?? 'Automated Requesting System');
                    $mail->addAddress($email);
                    $mail->Subject = 'Password Reset Request';
                    $mail->Body = "Click the link below to reset your password (expires in 1 hour):\n\n{$resetLink}";

                    $mail->send();
                } catch (Exception $e) {
                    // Silently fail — don't expose mail errors to user
                }
            }

            header('Location: ' . url('forgot-password'));
            exit;
        }

        public function updatePassword(): void {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

            \App\Helpers\Csrf::verify();

            $token = trim($_POST['token'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['password_confirmation'] ?? '';

            if (empty($token) || empty($password)) {
                $_SESSION['error'] = 'Invalid request.';
                header('Location: ' . url('login'));
                exit;
            }

            if ($password !== $confirm) {
                $_SESSION['error'] = 'Passwords do not match.';
                header("Location: " . url("reset-password?token=") . urlencode($token));
                exit;
            }

            if (strlen($password) < 8) {
                $_SESSION['error'] = 'Password must be at least 8 characters.';
                header("Location: " . url("reset-password?token=") . urlencode($token));
                exit;
            }

            $stmt = db()->prepare(
                'SELECT id, employee_id FROM password_reset_tokens
                WHERE token = ? AND used = 0 AND expires_at > NOW()'
            );
            $stmt->execute([$token]);
            $row = $stmt->fetch();

            if (!$row) {
                $_SESSION['login_error'] = 'Reset link is invalid or has expired.';
                header('Location: ' . url('forgot-password'));
                exit;
            }

            db()->prepare('UPDATE employees SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $row['employee_id']]);

            db()->prepare('UPDATE password_reset_tokens SET used = 1 WHERE id = ?')
            ->execute([$row['id']]);

            $_SESSION['success'] = 'Password updated. You can now log in.';
            header('Location: ' . url('login'));
            exit;
        }
    }