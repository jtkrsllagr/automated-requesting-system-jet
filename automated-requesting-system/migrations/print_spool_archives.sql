-- Retained Windows print-spool files and optional PDF conversions.
ALTER TABLE print_activities
    ADD COLUMN IF NOT EXISTS print_job_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ADD INDEX IF NOT EXISTS idx_print_activity_job (source_computer, print_job_id);

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
