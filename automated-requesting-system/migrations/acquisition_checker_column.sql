ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS acquisition_checker_id INT NULL DEFAULT NULL,
    ADD CONSTRAINT fk_employees_acquisition_checker
        FOREIGN KEY (acquisition_checker_id) REFERENCES employees(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_employees_acquisition_checker ON employees(acquisition_checker_id);