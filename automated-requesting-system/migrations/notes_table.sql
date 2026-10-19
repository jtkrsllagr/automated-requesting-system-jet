-- ============================================================
-- NOTES TABLE
-- Per-user sticky notes used by the Tools > Notes widget.
-- Run once: import via phpMyAdmin / mysql CLI, same as the
-- other files in this migrations/ folder.
-- ============================================================

CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL DEFAULT '',
    content TEXT NOT NULL DEFAULT '',
    color VARCHAR(20) NOT NULL DEFAULT 'yellow',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notes_user (user_id),
    CONSTRAINT fk_notes_user FOREIGN KEY (user_id) REFERENCES employees(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
