-- ============================================================
-- HR ATTENDANCE VERIFIER
-- Adds the HRVerifier role and a per-employee hr_verifier_id link
-- (assigned by the SysAdmin the same way Supervisor / Master Approver
-- are assigned). The HR Verifier co-signs the Process Approval stage
-- alongside the Finance/Accounting Checker on Reimbursement and
-- Liquidation forms — see FormController::HR_COSIGN_FORM_TYPES.
-- ============================================================

INSERT INTO roles (name, description)
SELECT 'HRVerifier', 'Cross-checks employee attendance records and co-signs the Process Approval stage on Reimbursement and Liquidation forms'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE name = 'HRVerifier');

ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS hr_verifier_id INT NULL AFTER master_approver_id;

ALTER TABLE employees
    ADD CONSTRAINT fk_employees_hr_verifier FOREIGN KEY (hr_verifier_id) REFERENCES employees(id) ON DELETE SET NULL;

CREATE INDEX idx_employees_hr_verifier ON employees(hr_verifier_id);