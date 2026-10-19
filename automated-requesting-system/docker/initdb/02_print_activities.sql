-- Create print event storage on fresh database volumes.
CREATE TABLE IF NOT EXISTS print_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_computer VARCHAR(255) NOT NULL,
    event_record_id BIGINT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL DEFAULT 307,
    provider_name VARCHAR(255) NOT NULL DEFAULT '',
    level_display_name VARCHAR(64) NOT NULL DEFAULT '',
    event_message TEXT NOT NULL,
    event_xml LONGTEXT NOT NULL,
    printed_at DATETIME NOT NULL,
    document_name VARCHAR(512) NOT NULL DEFAULT '',
    print_job_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    user_name VARCHAR(255) NOT NULL DEFAULT '',
    client_computer VARCHAR(255) NOT NULL DEFAULT '',
    printer_name VARCHAR(255) NOT NULL DEFAULT '',
    port_name VARCHAR(255) NOT NULL DEFAULT '',
    pages INT UNSIGNED NOT NULL DEFAULT 0,
    job_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_print_activity_event (source_computer, event_record_id, printed_at),
    INDEX idx_print_activity_printed_at (printed_at),
    INDEX idx_print_activity_user (user_name),
    INDEX idx_print_activity_client (client_computer),
    INDEX idx_print_activity_job (source_computer, print_job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS print_spool_archives (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    print_activity_id BIGINT UNSIGNED NOT NULL,
    storage_key CHAR(32) NOT NULL,
    pdf_storage_key CHAR(32) NULL,
    spool_format VARCHAR(32) NOT NULL,
    conversion_status VARCHAR(32) NOT NULL,
    spool_size_bytes BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_print_spool_activity (print_activity_id),
    INDEX idx_print_spool_archived_at (archived_at),
    CONSTRAINT fk_print_spool_activity FOREIGN KEY (print_activity_id)
        REFERENCES print_activities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
