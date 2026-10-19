<?php
class CompanyController {
    private function requireAdmin(): void {
        if ((int)($_SESSION['role_id'] ?? 0) !== 1) {
            $_SESSION['error'] = 'Access denied.';
            header('Location: ' . url('dashboard'));
            exit;
        }
    }

    public function index(): void {

        $this->requireAdmin();

        $companies = db()->query(
            'SELECT c.*, COUNT(DISTINCT e.id) AS member_count
            FROM companies c
            LEFT JOIN employees e ON e.company = c.name AND e.is_active = 1
            GROUP BY c.id ORDER BY c.name ASC'
        )->fetchAll();

        define('BASE_LOADED', true);
        ob_start();

        require __DIR__ . '/../../views/companies/index.php';
        $content = ob_get_clean();
        $pageTitle = 'Companies';
        require __DIR__ . '/../../views/layouts/base.php';
    }

    public function members(int $id): void {
        $this->requireAdmin();
        header('Content-Type: application/json');
        $rows = db()->prepare(
            'SELECT id, full_name, employee_code, role_id
            FROM employees
            WHERE company = (SELECT name FROM companies WHERE id = ?)
            AND is_active = 1
            ORDER BY full_name ASC'
        );
        $rows->execute([$id]);
        echo json_encode($rows->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    public function store(): void {
        \App\Helpers\Csrf::verify();
        $this->requireAdmin();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $_SESSION['error'] = 'Company name is required.';
            header('Location: ' . url('companies'));
            exit;
        }
        try {
            db()->prepare('INSERT INTO companies (name) VALUES (?)')->execute([$name]);
            $newId = (int) db()->lastInsertId();
            $_SESSION['success'] = 'Company added.';
            $_SESSION['last_company_id'] = $newId;

            if (!empty($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $logoResult = $this->saveLogo($newId, $_FILES['logo']);
                if ($logoResult !== '') {
                    $_SESSION['success'] = 'Company added, but logo upload failed: ' . $logoResult;
                }
            }
        } catch (\Throwable) {
            $_SESSION['error'] = 'Company already exists.';
        }
        header('Location: ' . url('companies'));
        exit;
    }

    public function update(int $id): void {
        \App\Helpers\Csrf::verify();
        $this->requireAdmin();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $_SESSION['error'] = 'Company name is required.';
            $_SESSION['last_company_id'] = $id;
            header('Location: ' . url('companies'));
            exit;
        }
        try {
            db()->prepare('UPDATE companies SET name = ? WHERE id = ?')->execute([$name, $id]);
            $_SESSION['success'] = 'Company updated.';
        } catch (\Throwable) {
            $_SESSION['error'] = 'That name is already in use.';
        }
        $_SESSION['last_company_id'] = $id;
        header('Location: ' . url('companies'));
        exit;
    }

    public function delete(int $id): void {
        \App\Helpers\Csrf::verify();
        $this->requireAdmin();

        $old = db()->prepare('SELECT logo FROM companies WHERE id = ?');
        $old->execute([$id]);
        $oldLogo = $old->fetchColumn();
        $destDir = __DIR__ . '/../../public/uploads/companies/';
        if ($oldLogo && file_exists($destDir . basename($oldLogo))) {
            unlink($destDir . basename($oldLogo));
        }

        db()->prepare('DELETE FROM companies WHERE id = ?')->execute([$id]);
        $_SESSION['success'] = 'Company deleted.';
        header('Location: ' . url('companies'));
        exit;
    }

    public function uploadLogo(int $id): void {
        \App\Helpers\Csrf::verify();
        $this->requireAdmin();

        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'No file uploaded or upload error.';
            $_SESSION['last_company_id'] = $id;
            header('Location: ' . url('companies'));
            exit;
        }

        $result = $this->saveLogo($id, $_FILES['logo']);

        $_SESSION[$result === '' ? 'success' : 'error'] = $result === '' ? 'Company logo updated.' : $result;
        $_SESSION['last_company_id'] = $id;
        header('Location: ' . url('companies'));
        exit;
    }

    /**
     * Validates and saves an uploaded logo for a company.
     * Returns an empty string on success, or an error message on failure.
     */
    private function saveLogo(int $id, array $file): string {
        $maxSize = 2 * 1024 * 1024; // 2MB
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/gif'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if ($file['size'] > $maxSize) {
            return 'Image must be under 2 MB.';
        }
        if (!in_array($mimeType, $allowed, true)) {
            return 'Only JPEG, JPG, PNG, WEBP, or GIF images are allowed.';
        }

        $extMap = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $ext = $extMap[$mimeType];

        $filename = 'company_' . $id . '_' . time() . '.' . $ext;
        $destDir = __DIR__ . '/../../public/uploads/companies/';
        $destPath = $destDir . $filename;

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // Delete old logo file if it exists
        $old = db()->prepare('SELECT logo FROM companies WHERE id = ?');
        $old->execute([$id]);
        $oldLogo = $old->fetchColumn();
        if ($oldLogo && file_exists($destDir . basename($oldLogo))) {
            unlink($destDir . basename($oldLogo));
        }

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return 'Failed to save image.';
        }

        db()->prepare('UPDATE companies SET logo = ? WHERE id = ?')->execute([$filename, $id]);
        return '';
    }
}
