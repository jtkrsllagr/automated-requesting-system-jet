-- FormController.php joins form_data on every form read (findForm(), show(),
-- edit) and writes new submissions there (store()), but the table was never
-- added as a standalone migration for databases created before it existed.
-- Every "view form" or "edit form" request fails with:
--   Base table or view not found: 1146 Table 'form_data' doesn't exist
-- until this is applied.

CREATE TABLE IF NOT EXISTS form_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id BIGINT NOT NULL,
    data LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_form_data_form_id (form_id),
    CONSTRAINT fk_form_data_form FOREIGN KEY (form_id) REFERENCES forms(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: forms.data (the legacy column FormController::rawFormData()
-- falls back to) may still hold submitted values for forms created before
-- this table existed. Copy anything not already present so old submissions
-- keep showing their data instead of appearing blank.
INSERT INTO form_data (form_id, data)
SELECT f.id, f.data
FROM forms f
WHERE f.data IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM form_data fd WHERE fd.form_id = f.id);
