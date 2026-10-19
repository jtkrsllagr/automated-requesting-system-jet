<?php
    // Session security
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax'); // Strict blocks cookie on cross-origin POST redirects
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.name', 'ARS_SESSION'); // Explicit name avoids collisions with other PHP apps

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // DB
    require_once __DIR__ . '/database.php';

    // Timezone
    date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Manila');

    // URL helper — generates root-relative paths (e.g. /forms/advance-payment)
    // so links work regardless of which IP/hostname the browser uses.
    // Docker: APP_URL=https://192.168.100.108 → subpath = empty
    // Non-Docker: APP_URL=https://host/automated-requesting-system/public → subpath extracted
    if (!function_exists('url')) {
        function url(string $path = ''): string {
            $appUrl = $_ENV['APP_URL'] ?? '';
            $subPath = rtrim(parse_url($appUrl, PHP_URL_PATH) ?? '', '/');
            return $subPath . '/' . ltrim($path, '/');
        }
    }

    // Avatar helper - turns a stored avatar filename (or full URL) into a
    // browser-ready, escaped src, or null if the employee has no avatar.
    if (!function_exists('avatar_url')) {
        function avatar_url(?string $avatar): ?string {
            $av = ltrim((string) $avatar, '/');
            if ($av === '' || $av === 'default.png') return null;
            if (!str_starts_with($av, 'http')) {
                if (!str_Starts_with($av, 'uploads/avatars/')) $av = 'uploads/avatars/' . $av;
                $av = url($av);
            }

            return htmlspecialchars($av);
        }
    }
