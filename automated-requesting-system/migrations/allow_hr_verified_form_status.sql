-- Allow HR Verification to advance forms to the hr_verified pipeline status.
SET @forms_status_check = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'forms'
      AND CONSTRAINT_TYPE = 'CHECK'
    LIMIT 1
);

SET @forms_status_check_sql = IF(
    @forms_status_check IS NULL,
    'ALTER TABLE forms ADD CONSTRAINT chk_forms_status CHECK (status IN (''draft'', ''submitted'', ''immediatehead_approved'', ''hr_verified'', ''department_reviewed'', ''process_approved'', ''finance_reviewed'', ''final_approved'', ''completed'', ''rejected'', ''cancelled''))',
    CONCAT(
        'ALTER TABLE forms DROP CHECK `',
        REPLACE(@forms_status_check, '`', '``'),
        '`, ADD CONSTRAINT chk_forms_status CHECK (status IN (''draft'', ''submitted'', ''immediatehead_approved'', ''hr_verified'', ''department_reviewed'', ''process_approved'', ''finance_reviewed'', ''final_approved'', ''completed'', ''rejected'', ''cancelled''))'
    )
);

PREPARE forms_status_check_stmt FROM @forms_status_check_sql;
EXECUTE forms_status_check_stmt;
DEALLOCATE PREPARE forms_status_check_stmt;
