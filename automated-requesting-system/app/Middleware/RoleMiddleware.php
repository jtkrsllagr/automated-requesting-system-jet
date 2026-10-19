<?php
    namespace App\Middleware;

    class RoleMiddleware {
        public static function requireRole(int $roleId): void {
            if (session_status() === PHP_SESSION_NONE) session_start();

            if (($_SESSION['role_id'] ?? 0) != $roleId) {
                http_response_code(403);
                echo '<h3>403 - Access Denied</h3>';
                exit;
            }
        }

        // Allow multiple roles — e.g. requireAnyRole([1, 2, 4, 5, 6])
        public static function requireAnyRole(array $roleIds): void {
            if (session_status() === PHP_SESSION_NONE) session_start();

            if (!in_array((int)($_SESSION['role_id'] ?? 0), $roleIds, true)) {
                http_response_code(403);
                echo '<h3>403 - Access Denied</h3>';
                exit;
            }
        }
    }
