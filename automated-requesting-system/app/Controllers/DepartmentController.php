<?php
class DepartmentController {
    private function requireAdmin(): void {
        if ((int)($_SESSION['role_id'] ?? 0) !== 1) {
            $_SESSION['error'] = 'Access denied.';
            header('Location: ' . url('dashboard'));
            exit;
        }
    }

    public function index(): void {

        $this->requireAdmin();

        $departments = db()->query(
            'SELECT d.*, COUNT(DISTINCT e.id) AS member_count,
                    COUNT(DISTINCT f.id) AS form_count
            FROM departments d
            LEFT JOIN employees e ON e.department = d.name AND e.is_active = 1
            LEFT JOIN forms f ON f.submitted_by = e.id
                            AND f.status NOT IN ("draft","cancelled","completed","rejected")
            GROUP BY d.id ORDER BY d.name ASC'
        )->fetchAll();
        
        define('BASE_LOADED', true);
        ob_start();

        require __DIR__ . '/../../views/departments/index.php';
        $content = ob_get_clean();
        $pageTitle = 'Departments';
        require __DIR__ . '/../../views/layouts/base.php';
    }

    public function members(int $id): void {
        $this->requireAdmin();
        header('Content-Type: application/json');
        $rows = db()->prepare(
            'SELECT id, full_name, employee_code, role_id
            FROM employees
            WHERE department = (SELECT name FROM departments WHERE id = ?)
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
            $_SESSION['error'] = 'Department name is required.';
            header('Location: ' . url('departments'));
            exit;
        }
        try {
            db()->prepare('INSERT INTO departments (name) VALUES (?)')->execute([$name]);
            $_SESSION['success'] = 'Department added.';
            $_SESSION['last_dept_id'] = (int) db()->lastInsertId();
        } catch (\Throwable) {
            $_SESSION['error'] = 'Department already exists.';
        }
        header('Location: ' . url('departments'));
        exit;
    }

    public function update(int $id): void {
        \App\Helpers\Csrf::verify();
        $this->requireAdmin();
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $_SESSION['error'] = 'Department name is required.';
            $_SESSION['last_dept_id'] = $id;
            header('Location: ' . url('departments'));
            exit;
        }
        try {
            db()->prepare('UPDATE departments SET name = ? WHERE id = ?')->execute([$name, $id]);
            $_SESSION['success'] = 'Department updated.';
        } catch (\Throwable) {
            $_SESSION['error'] = 'That name is already in use.';
        }
        $_SESSION['last_dept_id'] = $id;
        header('Location: ' . url('departments'));
        exit;
    }

    public function delete(int $id): void {
        \App\Helpers\Csrf::verify();
        $this->requireAdmin();
        db()->prepare('DELETE FROM departments WHERE id = ?')->execute([$id]);
        $_SESSION['success'] = 'Department deleted.';
        header('Location: ' . url('departments'));
        exit;
    }
}