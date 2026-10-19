<?php
    namespace App\Controllers;

    class RealtimeController {
        public function token(): void {
            header('Content-Type: application/json');
            header('Cache-Control: no-store');

            $userId = (int) ($_SESSION['user_id'] ?? 0);
            $roleId = (int) ($_SESSION['role_id'] ?? 0);
            if ($userId < 1) {
                http_response_code(403);
                echo json_encode(['error' => 'Realtime access is not available.']);
                exit;
            }

            try {
                echo json_encode([
                    'token' => \App\Services\RealtimeEventService::issueToken($userId, $roleId),
                ]);
            } catch (\Throwable $exception) {
                http_response_code(503);
                echo json_encode(['error' => 'Realtime updates are temporarily unavailable.']);
            }
            exit;
        }
    }