-- Add full-event fields to installations created before all-event collection.
ALTER TABLE print_activities
    ADD COLUMN IF NOT EXISTS provider_name VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS level_display_name VARCHAR(64) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS event_message TEXT NOT NULL,
    ADD COLUMN IF NOT EXISTS event_xml LONGTEXT NOT NULL;

ALTER TABLE print_activities
    ADD COLUMN IF NOT EXISTS print_job_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ADD INDEX IF NOT EXISTS idx_print_activity_job (source_computer, print_job_id);
