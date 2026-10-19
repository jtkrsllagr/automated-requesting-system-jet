-- ============================================================
-- ARS Chat Integration — ARS database
-- ============================================================

CREATE TABLE IF NOT EXISTS chat_blocked_users (
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (blocker_id, blocked_id),
    CONSTRAINT fk_cbu_blocker FOREIGN KEY (blocker_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_cbu_blocked FOREIGN KEY (blocked_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_blocked_users (
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (blocker_id, blocked_id),
    CONSTRAINT fk_cbu_blocker FOREIGN KEY (blocker_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_cbu_blocked FOREIGN KEY (blocked_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_typing (
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (sender_id, receiver_id),
    INDEX idx_typing_receiver (receiver_id),
    CONSTRAINT fk_typing_sender FOREIGN KEY (sender_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_typing_receiver FOREIGN KEY (receiver_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_reactions (
    message_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id, user_id),
    CONSTRAINT fk_cr_message FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_cr_user FOREIGN KEY (user_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL DEFAULT NULL;
 
ALTER TABLE employees
    ADD INDEX IF NOT EXISTS idx_employees_last_seen (last_seen_at);