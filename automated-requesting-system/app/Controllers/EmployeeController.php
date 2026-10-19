<?php
    class EmployeeController {
        public function index(): void {
            if ((int)($_SESSION['role_id'] ?? 0) !== 1) {
                $_SESSION['error'] = 'Access denied. Administrator privileges required.';
                header('Location: ' . url('dashboard'));
                exit;
            }

            $employees = db()->query(
                'SELECT e.*, r.name as role_name, s.full_name as supervisor_name,
                        COUNT(a.id) AS pending_approvals
                FROM employees e
                LEFT JOIN roles r ON e.role_id = r.id
                LEFT JOIN employees s ON e.supervisor_id = s.id
                LEFT JOIN approvals a ON a.approver_id = e.id AND a.status = "pending"
                WHERE e.employment_status != "resigned" OR e.is_active = 1
                GROUP BY e.id
                ORDER BY e.is_active DESC, e.full_name ASC'
            )->fetchAll();
            define('BASE_LOADED', true);
            ob_start();
            require __DIR__ . '/../../views/employees/index.php';
            $content   = ob_get_clean();
            $pageTitle = 'Employees';
            require __DIR__ . '/../../views/layouts/base.php';
        }

        public function create(): void {
            if ((int)($_SESSION['role_id'] ?? 0) !== 1) {
                $_SESSION['error'] = 'Access denied.';
                header('Location: ' . url('dashboard'));
                exit;
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->store();
                return;
            }

            $supervisors = db()->query('SELECT id, full_name FROM employees WHERE role_id IN (2, 4, 7, 8) AND is_active = 1 ORDER BY full_name')->fetchAll();
            $masterApprovers = db()->query('SELECT id, full_name FROM employees WHERE role_id IN (4, 7) AND is_active = 1 ORDER BY full_name')->fetchAll();
            $hrVerifiers = db()->query('SELECT id, full_name FROM employees WHERE role_id = 9 AND is_active = 1 ORDER BY full_name')->fetchAll();
            $financeHeads = db()->query('SELECT id, full_name FROM employees WHERE role_id = 8 AND is_active = 1 ORDER BY full_name')->fetchAll();
            $roles = db()->query('SELECT id, name FROM roles ORDER BY id ASC')->fetchAll();
            $departments = db()->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();
            $companies = db()->query('SELECT id, name FROM companies ORDER BY name')->fetchAll();

            define('BASE_LOADED', true);
            ob_start();
            require __DIR__ . '/../../views/employees/create.php';
            $content   = ob_get_clean();
            $pageTitle = 'Add Employee';
            require __DIR__ . '/../../views/layouts/base.php';
        }

        private function store(): void {
            \App\Helpers\Csrf::verify();

            $data = [];
            foreach (['full_name', 'username', 'email', 'password', 'role_id', 'department', 'company',
                      'supervisor_id', 'supervisor_id_2', 'master_approver_id', 'finance_head_id',
                      'job_title', 'phone', 'date_hired', 'employment_type'] as $f) {
                $val = trim($_POST[$f] ?? '');
                if ($val === '' && !in_array($f, ['department', 'supervisor_id', 'supervisor_id_2',
                    'master_approver_id', 'finance_head_id', 'username', 'job_title',
                    'phone', 'date_hired', 'employment_type', 'company'])) {
                    $_SESSION['error'] = "Field '{$f}' is required.";
                    header('Location: ' . url('employees/create'));
                    exit;
                }
                $data[$f] = $val;
            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'Invalid email address.';
                header('Location: ' . url('employees/create'));
                exit;
            }

            if ($data['supervisor_id_2'] !== '' && $data['supervisor_id_2'] === $data['supervisor_id']) {
                $_SESSION['error'] = 'Second supervisor must be different from the primary supervisor.';
                header('Location: ' . url('employees/create'));
                exit;
            }

            if (strlen($data['password']) < 8) {
                $_SESSION['error'] = 'Password must be at least 8 characters.';
                header('Location: ' . url('employees/create'));
                exit;
            }

            $stmt = db()->prepare('SELECT id, is_active FROM employees WHERE email = ?');
            $stmt->execute([$data['email']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing && $existing['is_active'] == 1) {
                $_SESSION['error'] = 'Email is already in use by an active employee.';
                header('Location: ' . url('employees/create'));
                exit;
            }

            if ($existing && $existing['is_active'] == 0) {
                try {
                    $pdo     = db();
                    $empCode = \App\Helpers\generateEmployeeCode($pdo);
                    $pdo->prepare(
                        'UPDATE employees SET
                            employee_code = ?, full_name = ?, username = ?, email = ?,
                            password_hash = ?, role_id = ?, department = ?, company = ?,
                            supervisor_id = ?, supervisor_id_2 = ?, master_approver_id = ?,
                            finance_head_id = ?, job_title = ?, phone = ?, date_hired = ?,
                            employment_type = ?, employment_status = \'employed\', is_active = 1
                        WHERE id = ?'
                    )->execute([
                        $empCode,
                        $data['full_name'],
                        $data['username'] ?: null,
                        $data['email'],
                        password_hash($data['password'], PASSWORD_BCRYPT),
                        (int) $data['role_id'],
                        $data['department'] ?: null,
                        $data['company'] ?: null,
                        $data['supervisor_id'] ? (int) $data['supervisor_id'] : null,
                        $data['supervisor_id_2'] ? (int) $data['supervisor_id_2'] : null,
                        $data['master_approver_id'] ? (int) $data['master_approver_id']  : null,
                        $data['finance_head_id'] ? (int) $data['finance_head_id'] : null,
                        $data['job_title'] ?: null,
                        $data['phone'] ?: null,
                        $data['date_hired'] ?: null,
                        $data['employment_type'] ?: 'full_time',
                        (int) $existing['id'],
                    ]);
                } catch (\Throwable $e) {
                    $_SESSION['error'] = 'Failed to reactivate employee: ' . $e->getMessage();
                    header('Location: ' . url('employees/create'));
                    exit;
                }

                \App\Services\RealtimeEventService::publishPendingCountChanged();
                $_SESSION['success'] = 'Employee account reactivated with new details.';
                header('Location: ' . url('employees'));
                exit;
            }
            // new employee
            try {
                $pdo = db();
                $empCode = \App\Helpers\generateEmployeeCode($pdo);
                $pdo->prepare(
                    'INSERT INTO employees
                        (employee_code, full_name, username, email, password_hash, role_id,
                        department, company, supervisor_id, supervisor_id_2, master_approver_id,
                        finance_head_id, job_title, phone, date_hired,
                        employment_type, employment_status, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'employed\', 1)'
                )->execute([
                    $empCode,
                    $data['full_name'],
                    $data['username'] ?: null,
                    $data['email'],
                    password_hash($data['password'], PASSWORD_BCRYPT),
                    (int) $data['role_id'],
                    $data['department'] ?: null,
                    $data['company'] ?: null,
                    $data['supervisor_id'] ? (int) $data['supervisor_id'] : null,
                    $data['supervisor_id_2'] ? (int) $data['supervisor_id_2'] : null,
                    $data['master_approver_id'] ? (int) $data['master_approver_id'] : null, 
                    $data['finance_head_id'] ? (int) $data['finance_head_id'] : null,
                    $data['job_title'] ?: null,
                    $data['phone'] ?: null,
                    $data['date_hired'] ?: null, 
                    $data['employment_type'] ?: 'full_time',
                ]);
            } catch (\Throwable $e) {
                $_SESSION['error'] = 'Failed to create employee: ' . $e->getMessage();
                header('Location: ' . url('employees/create'));
                exit;
            }

            \App\Services\RealtimeEventService::publishPendingCountChanged();
            $_SESSION['success'] = 'Employee created.';
            header('Location: ' . url('employees'));
            exit;
        }

        public function edit(int $id): void {
            if ((int)($_SESSION['role_id'] ?? 0) !== 1) {
                $_SESSION['error'] = 'Access denied.';
                header('Location: ' . url('dashboard')); exit;
            }

            $stmt = db()->prepare('SELECT * FROM employees WHERE id = ?');
            $stmt->execute([$id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            $departments = db()->query('SELECT id, name FROM departments ORDER BY name')->fetchAll(); 
            $companies = db()->query('SELECT id, name FROM companies ORDER BY name')->fetchAll();

            if (!$employee) {
                $_SESSION['error'] = 'Employee not found.';
                header('Location: ' . url('employees')); exit;
            }

            $supervisors = db()->query('SELECT id, full_name FROM employees WHERE role_id IN (2, 4, 7, 8) AND is_active = 1 AND id != ' . (int)$id . ' ORDER BY full_name')->fetchAll();
            $masterApprovers = db()->query('SELECT id, full_name FROM employees WHERE role_id IN (4, 7) AND is_active = 1 AND id != ' . (int)$id . ' ORDER BY full_name')->fetchAll();
            $financeHeads = db()->query('SELECT id, full_name FROM employees WHERE role_id = 8 AND is_active = 1 AND id != ' . (int)$id . ' ORDER BY full_name')->fetchAll();
            $hrVerifiers = db()->query('SELECT id, full_name FROM employees WHERE role_id = 9 AND is_active = 1 ORDER BY full_name')->fetchAll();
            $roles = db()->query('SELECT * FROM roles ORDER BY id ASC')->fetchAll();

            define('BASE_LOADED', true);
            ob_start();
            require __DIR__ . '/../../views/employees/edit.php'; 
            $content = ob_get_clean();
            $pageTitle = 'Edit Employee: ' . htmlspecialchars($employee['full_name']);
            require __DIR__ . '/../../views/layouts/base.php';
        }

        public function update(int $id): void {  
            \App\Helpers\Csrf::verify();
            if ((int)($_SESSION['role_id'] ?? 0) !== 1) { exit; }

            $data = [];
            foreach (['full_name', 'username', 'email', 'role_id', 'department', 'company',
                      'supervisor_id', 'supervisor_id_2', 'master_approver_id', 'finance_head_id',
                      'is_active', 'job_title', 'phone', 'date_hired', 'employment_type'] as $f) { 
                $data[$f] = trim($_POST[$f] ?? ''); 
            }

            if (empty($data['full_name']) || empty($data['email'])) {
                $_SESSION['error'] = "Name and Email are required.";
                header("Location: " . url("employees/edit/{$id}"));
                exit;
            }

            if ($data['supervisor_id_2'] !== '' && $data['supervisor_id_2'] === $data['supervisor_id']) {
                $_SESSION['error'] = 'Second supervisor must be different from the primary supervisor.';
                header("Location: " . url("employees/edit/{$id}"));
                exit;
            }

            $pdo = db();
            try {
                $sql = "UPDATE employees SET
                        full_name = ?, username = ?, email = ?, role_id = ?,
                        department = ?, company = ?, supervisor_id = ?, supervisor_id_2 = ?,
                        master_approver_id = ?, finance_head_id = ?, is_active = ?,
                        job_title = ?, phone = ?, date_hired = ?, employment_type = ?";
                $params = [
                    $data['full_name'],
                    $data['username'] ?: null,
                    $data['email'],
                    (int) $data['role_id'],
                    $data['department'] ?: null,
                    $data['company'] ?: null,
                    $data['supervisor_id'] ? (int) $data['supervisor_id'] : null,
                    $data['supervisor_id_2'] ? (int) $data['supervisor_id_2'] : null,
                    $data['master_approver_id'] ? (int) $data['master_approver_id'] : null,
                    $data['finance_head_id'] ? (int) $data['finance_head_id'] : null,
                    isset($_POST['is_active']) ? 1 : 0,
                    $data['job_title'] ?: null,
                    $data['phone'] ?: null,
                    $data['date_hired'] ?: null,
                    $data['employment_type'] ?: 'full_time',
                ];

                if (!empty($data['username'])) {
                    $check = db()->prepare('SELECT id FROM employees WHERE username = ? AND id != ?');
                    $check->execute([$data['username'], $id]);
                    if ($check->fetch()) {
                        $_SESSION['error'] = 'That username is already taken.';
                        header("Location: " . url("employees/edit/{$id}"));
                        exit;
                    }
                }

                if (!empty($_POST['password'])) {
                    if (strlen($_POST['password']) < 8) {
                        throw new Exception("Password too short.");
                    }
                    $sql .= ", password_hash = ?";
                    $params[] = password_hash($_POST['password'], PASSWORD_BCRYPT);
                }

                $sql .= " WHERE id = ?";
                $params[] = $id;

                $pdo->prepare($sql)->execute($params);
                \App\Services\RealtimeEventService::publishPendingCountChanged();
                $_SESSION['success'] = 'Employee updated successfully.';
            } catch (\Throwable $e) {
                $_SESSION['error'] = 'Update failed: ' . $e->getMessage();
                header("Location: " . url("employees/edit/{$id}"));
                exit;
            }

            header('Location: ' . url('employees'));
            exit;
        }

        // --- all methods below are unchanged from original ---

        public function delete(int $id): void {
            \App\Helpers\Csrf::verify();

            if ((int)($_SESSION['role_id'] ?? 0) !== 1) {
                $_SESSION['error'] = 'Access denied.';
                header('Location: ' . url('dashboard'));
                exit;
            }

            if ($id === (int)$_SESSION['user_id']) {
                $_SESSION['error'] = 'You cannot delete your own account.';
                header('Location: ' . url('employees'));
                exit;
            }

            $pdo = db();
            $stmt = $pdo->prepare('SELECT id, role_id FROM employees WHERE id = ?');
            $stmt->execute([$id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$employee) {
                $_SESSION['error'] = 'Employee not found.';
                header('Location: ' . url('employees'));
                exit;
            }

            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE employees SET is_active = 0 WHERE id = ?')->execute([$id]);

                $pendingApprovals = $pdo->prepare(
                    'SELECT id FROM approvals WHERE approver_id = ? AND status = \'pending\''
                );
                $pendingApprovals->execute([$id]);
                $rows = $pendingApprovals->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $replacement = $pdo->prepare(
                        'SELECT e.id, COUNT(a.id) AS workload
                         FROM employees e
                         LEFT JOIN approvals a ON a.approver_id = e.id AND a.status = \'pending\'
                         WHERE e.role_id = ? AND e.is_active = 1 AND e.id != ?
                         GROUP BY e.id
                         ORDER BY workload ASC, e.id ASC
                         LIMIT 1'
                    );
                    $replacement->execute([$employee['role_id'], $id]);
                    $replacementEmployee = $replacement->fetch(PDO::FETCH_ASSOC);

                    if ($replacementEmployee) {
                        $pdo->prepare(
                            'UPDATE approvals SET approver_id = ? WHERE approver_id = ? AND status = \'pending\''
                        )->execute([$replacementEmployee['id'], $id]);
                        $_SESSION['success'] = sprintf(
                            'Employee deactivated. %d pending approval(s) reassigned.',
                            count($rows)
                        );
                    } else {
                        $_SESSION['success'] = 'Employee deactivated. Warning: no replacement approver found for their ' . count($rows) . ' pending step(s). Please reassign manually.';
                    }
                } else {
                    $_SESSION['success'] = 'Employee deactivated.';
                }

                $pdo->commit();
                \App\Services\RealtimeEventService::publishPendingCountChanged();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $_SESSION['error'] = 'Failed to deactivate employee.';
            }

            header('Location: ' . url('employees'));
            exit;
        }

        public function updateStatus(int $id): void {
            \App\Helpers\Csrf::verify();

            if ((int)($_SESSION['role_id'] ?? 0) !== 1) {
                $_SESSION['error'] = 'Access denied.';
                header('Location: ' . url('dashboard'));
                exit;
            }

            $allowed = ['employed', 'resigned', 'floating'];
            $status = trim($_POST['employment_status'] ?? '');

            if (!in_array($status, $allowed, true)) {
                $_SESSION['error'] = 'Invalid employment status.';
                header('Location: ' . url('employees'));
                exit;
            }

            db()->prepare('UPDATE employees SET employment_status = ? WHERE id = ?')
                ->execute([$status, $id]);

            $_SESSION['success'] = 'Employment status updated.';
            header('Location: ' . url('employees'));
            exit;
        }

        public function actAsApprover(int $formId, string $action): void {
            \App\Helpers\Csrf::verify();

            if ((int)($_SESSION['role_id'] ?? 0) !== 1) {
                $_SESSION['error'] = 'Access denied. Only SysAdmins can perform manual overrides.';
                header("Location: " . url("forms/view/{$formId}"));
                exit;
            }

            if (!in_array($action, ['approved', 'rejected'], true)) {
                http_response_code(400);
                exit;
            }

            $stmt = db()->prepare('SELECT status FROM forms WHERE id = ?');
            $stmt->execute([$formId]);
            $form = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$form) {
                $_SESSION['error'] = 'Form not found.';
                header("Location: " . url("forms/view/{$formId}")); 
                exit;
            }
            $remarks = trim($_POST['remarks'] ?? '(SysAdmin override)');

            $step = db()->prepare(
                'SELECT * FROM approvals WHERE form_id = ? AND status = \'pending\' ORDER BY sequence LIMIT 1'
            );
            $step->execute([$formId]);
            $approval = $step->fetch();

            $pdo = db();
            $pdo->beginTransaction();

            try {
                if ($approval) {
                    $pdo->prepare(
                        'UPDATE approvals SET status = ?, remarks = ?, approved_at = NOW() WHERE id = ?'
                    )->execute([$action, $remarks, $approval['id']]);
                }

                if ($action === 'rejected') {
                    $newStatus = 'rejected';
                    $pdo->prepare(
                        "UPDATE approvals SET status = 'rejected', remarks = ?, approved_at = NOW()
                         WHERE form_id = ? AND status = 'pending' AND id != ?"
                    )->execute([$remarks, $formId, $approval['id']]);
                } else {
                    $formTypeRow = db()->prepare('SELECT form_type FROM forms WHERE id = ?');
                    $formTypeRow->execute([$formId]);
                    $formType = $formTypeRow->fetchColumn();

                    $adminForms = ['overtime_authorization', 'leave_application', 'vehicle_request'];
                    $isAdminForm = in_array($formType, $adminForms, true);

                    $seqToStatus = $isAdminForm
                        ? [
                            2 => 'immediatehead_approved',
                            3 => 'department_reviewed',
                            4 => 'completed',
                        ]
                        : [
                            2 => 'immediatehead_approved',
                            3 => 'process_approved',
                            4 => 'finance_reviewed',
                            5 => 'completed',
                        ];

                    $seq = (int) ($approval['sequence'] ?? 0);
                    $newStatus = $seqToStatus[$seq] ?? 'submitted';
                }

                $pdo->prepare('UPDATE forms SET status = ? WHERE id = ?')->execute([$newStatus, $formId]);

                $pdo->prepare(
                    'INSERT INTO audit_logs (performed_by, action, entity_type, entity_id, old_values, new_values, ip_address)
                    VALUES (?, ?, \'form\', ?, ?, ?, ?)'
                )->execute([
                    $_SESSION['user_id'],
                    "sysadmin_form_{$action}",
                    $formId,
                    json_encode(['status' => $form['status']]),
                    json_encode(['status' => $newStatus, 'remarks' => $remarks]),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]);

                $pdo->commit();
                \App\Services\RealtimeEventService::publishPendingCountChanged();
                $_SESSION['success'] = 'Form ' . $action . ' by SysAdmin.';
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $_SESSION['error'] = 'Action failed.';
            }

            header("Location: " . url("forms/view/{$formId}"));
            exit;
        }

        public function profile(): void {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->updateProfile();
                return;
            }
            $employee = db()->prepare('
                SELECT e.id, e.employee_code, e.full_name, e.email, e.department, e.company,
                    e.username, e.is_active, e.role_id, e.updated_at, e.avatar,
                    s.full_name AS supervisor_name
                FROM employees e
                LEFT JOIN employees s ON s.id = e.supervisor_id
                WHERE e.id = ?
            ');
            $employee->execute([$_SESSION['user_id']]);
            $employee = $employee->fetch();
            $departments = db()->query('SELECT id, name FROM departments ORDER BY name')->fetchAll();

            define('BASE_LOADED', true);
            ob_start();
            require __DIR__ . '/../../views/profile/index.php';
            $content = ob_get_clean();
            $pageTitle = 'Profile';
            require __DIR__ . '/../../views/layouts/base.php';
        }

        private function updateProfile(): void {
            \App\Helpers\Csrf::verify();

            $section = $_POST['section'] ?? 'account';

            if ($section === 'password') {
                if (empty($_POST['new_password'])) {
                    $_SESSION['error'] = 'Please enter a new password.';
                    header('Location: ' . url('profile'));
                    exit;
                }
                if (strlen($_POST['new_password']) < 8) {
                    $_SESSION['error'] = 'New password must be at least 8 characters.';
                    header('Location: ' . url('profile'));
                    exit;
                }
                if ($_POST['new_password'] !== ($_POST['confirm_password'] ?? '')) {
                    $_SESSION['error'] = 'Passwords do not match.';
                    header('Location: ' . url('profile'));
                    exit;
                }

                $emp = db()->prepare('SELECT password_hash FROM employees WHERE id = ?');
                $emp->execute([$_SESSION['user_id']]);
                $emp = $emp->fetch();

                if (!password_verify($_POST['current_password'] ?? '', $emp['password_hash'])) {
                    $_SESSION['error'] = 'Current password is incorrect.';
                    header('Location: ' . url('profile'));
                    exit;
                }

                db()->prepare('UPDATE employees SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($_POST['new_password'], PASSWORD_BCRYPT), $_SESSION['user_id']]);

                $_SESSION['success'] = 'Password updated.';
                header('Location: ' . url('profile'));
                exit;
            }

            $data = [
                'full_name' => trim($_POST['full_name'] ?? ''),
                'department' => trim($_POST['department'] ?? ''),
                'username' => trim($_POST['username'] ?? '') ?: null,
            ];

            if ($data['full_name'] === '') {
                $_SESSION['error'] = 'Full name is required.';
                header('Location: ' . url('profile'));
                exit;
            }

            if (!empty($data['username'])) {
                $check = db()->prepare('SELECT id FROM employees WHERE username = ? AND id != ?');
                $check->execute([$data['username'], $_SESSION['user_id']]);
                if ($check->fetch()) {
                    $_SESSION['error'] = 'That username is already taken.';
                    header('Location: ' . url('profile'));
                    exit;
                }
            }

            db()->prepare("UPDATE employees SET full_name = ?, department = ?, username = ? WHERE id = ?")
                ->execute([$data['full_name'], $data['department'], $data['username'], $_SESSION['user_id']]);

            $_SESSION['user_name'] = $data['full_name'];
            $_SESSION['success'] = 'Profile updated.';
            header('Location: ' . url('profile'));
            exit;
        }

        public function uploadAvatar(): void {
            \App\Helpers\Csrf::verify();

            if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['error'] = 'No file uploaded or upload error.';
                header('Location: ' . url('profile'));
                exit;
            }

            $file = $_FILES['avatar'];
            $maxSize = 2 * 1024 * 1024;
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/gif'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            if ($file['size'] > $maxSize) {
                $_SESSION['error'] = 'Image must be under 2 MB.';
                header('Location: ' . url('profile'));
                exit;
            }

            if (!in_array($mimeType, $allowed, true)) {
                $_SESSION['error'] = 'Only JPEG, JPG, PNG, WEBP, or GIF images are allowed.';
                header('Location: ' . url('profile'));
                exit;
            }

            $extMap = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!isset($extMap[$mimeType])) {
                $_SESSION['error'] = 'Unsupported image format.';
                header('Location: ' . url('profile'));
                exit;
            }
            $ext = $extMap[$mimeType];

            $filename = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            $destDir = __DIR__ . '/../../public/uploads/avatars/';
            $destPath = $destDir . $filename;

            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            $old = db()->prepare('SELECT avatar FROM employees WHERE id = ?');
            $old->execute([$_SESSION['user_id']]);
            $oldAvatar = $old->fetchColumn();
            if ($oldAvatar && file_exists($destDir . basename($oldAvatar))) {
                unlink($destDir . basename($oldAvatar));
            }

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $_SESSION['error'] = 'Failed to save image.';
                header('Location: ' . url('profile'));
                exit;
            }

            db()->prepare('UPDATE employees SET avatar = ? WHERE id = ?')
                ->execute([$filename, $_SESSION['user_id']]);

            $_SESSION['avatar'] = $filename;
            $_SESSION['success'] = 'Profile picture updated.';
            header('Location: ' . url('profile'));
            exit;
        }
    }
