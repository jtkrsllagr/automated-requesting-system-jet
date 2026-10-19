<?php
    function db(): PDO {
        static $pdo;
        if (!$pdo) {
            $dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_DATABASE']};charset=utf8mb4";
            $pdo = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                // Without this, MySQL writes CURRENT_TIMESTAMP columns (like
                // forms.created_at) using the DB server's own system timezone
                // (commonly UTC on Docker/cloud hosts), while config/app.php
                // sets PHP to Asia/Manila (UTC+8). That mismatch is what makes
                // "X ago" times in the dashboard look wrong by ~8 hours.
                // Aligning the MySQL session timezone to match PHP fixes it
                // for every timestamp column going forward.
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
            ]);
        }
        return $pdo;
    }