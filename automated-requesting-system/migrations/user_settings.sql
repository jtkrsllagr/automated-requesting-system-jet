-- ============================================================
-- USER-FACING SETTINGS
-- Adds keys for Themes, Notifications, and Storage Path.
-- ============================================================

INSERT IGNORE INTO settings (`key`, `value`) VALUES
    -- Themes
    ('theme_color', 'blue'),     
    ('theme_mode', 'light'), 
    ('sidebar_collapsed', '0'),   

    -- Notifications
    ('notify_on_submit', '1'), 
    ('notify_on_approval', '1'),  
    ('notify_on_rejection', '1'),   
    ('notify_on_completion', '1'),  

    -- Storage
    ('upload_path', 'public/uploads'), 
    ('max_file_size_mb', '10'),     
    ('allowed_file_types', 'pdf,jpg,png,docx'); 

CREATE TABLE IF NOT EXISTS user_settings (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_key (user_id, `key`),
    CONSTRAINT fk_us_user FOREIGN KEY (user_id) REFERENCES employees(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;