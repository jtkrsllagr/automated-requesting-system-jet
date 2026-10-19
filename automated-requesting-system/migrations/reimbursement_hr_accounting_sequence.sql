-- Split existing reimbursement HR/Accounting co-sign rows into ordered stages.
-- Run once before deploying the reimbursement pipeline change.
START TRANSACTION;

-- Move downstream rows out of their target sequences first to avoid the
-- (form_id, sequence, approver_id) unique constraint during reordering.
UPDATE approvals a
JOIN forms f ON f.id = a.form_id
JOIN approvals legacy ON legacy.form_id = f.id AND legacy.sequence = 3
JOIN employees legacy_employee ON legacy_employee.id = legacy.approver_id
SET a.sequence = a.sequence + 100
WHERE f.form_type = 'reimbursement'
  AND a.sequence >= 4
  AND legacy_employee.role_id = 5;

UPDATE approvals a
JOIN forms f ON f.id = a.form_id
JOIN approvals legacy ON legacy.form_id = f.id AND legacy.sequence = 3
JOIN employees legacy_employee ON legacy_employee.id = legacy.approver_id
SET a.sequence = a.sequence - 99
WHERE f.form_type = 'reimbursement'
  AND a.sequence >= 104
  AND legacy_employee.role_id = 5;

-- HR remains sequence 3; Accounting moves to sequence 4.
UPDATE approvals a
JOIN forms f ON f.id = a.form_id
JOIN employees e ON e.id = a.approver_id
SET a.sequence = 4
WHERE f.form_type = 'reimbursement'
  AND a.sequence = 3
  AND e.role_id = 5;

-- Resume in-flight requests at the state corresponding to their completed rows.
UPDATE forms f
SET f.status = CASE
    WHEN EXISTS (
        SELECT 1 FROM approvals a
        JOIN employees e ON e.id = a.approver_id
        WHERE a.form_id = f.id AND a.sequence = 3
          AND a.status = 'approved' AND e.role_id = 9
    ) AND EXISTS (
        SELECT 1 FROM approvals a
        JOIN employees e ON e.id = a.approver_id
        WHERE a.form_id = f.id AND a.sequence = 4
          AND a.status = 'pending' AND e.role_id = 5
    ) THEN 'process_approved'
    WHEN EXISTS (
        SELECT 1 FROM approvals a
        JOIN employees e ON e.id = a.approver_id
        WHERE a.form_id = f.id AND a.sequence = 3
          AND a.status = 'approved' AND e.role_id = 9
    ) AND EXISTS (
        SELECT 1 FROM approvals a
        JOIN employees e ON e.id = a.approver_id
        WHERE a.form_id = f.id AND a.sequence = 4
          AND a.status = 'approved' AND e.role_id = 5
    ) THEN 'process_approved'
    ELSE f.status
END
WHERE f.form_type = 'reimbursement'
  AND f.status = 'immediatehead_approved';

COMMIT;