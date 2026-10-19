-- ============================================================
-- SETTINGS TABLE
-- Key-value store for runtime application configuration.
-- Run once: php -r "require 'public/index.php';" or via phpMyAdmin.
-- ============================================================

CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NOT NULL DEFAULT '',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed with .env defaults so the form is pre-populated on first visit.
INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('app_name', 'AutomatedRequestingSystem'),
    ('app_url', 'https://localhost/automated-requesting-system/public'),
    ('app_env', 'local'),
    ('app_timezone', 'Asia/Manila'),
    ('items_per_page', '20'),
    ('session_lifetime', '120'),
    ('mail_host', 'smtp.gmail.com'),
    ('mail_port', '587'),
    ('mail_encryption', 'tls'),
    ('mail_username', ''),
    ('mail_password', ''),
    ('mail_from_address', ''),
    ('mail_from_name', 'Automated Requesting System');