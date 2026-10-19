-- DO NOT run as a migration. Read-only verification queries only.

-- ================================================================
-- Manual read-only checks - not part of the schema or migrations.
-- Run these in your DB client to verify the data integrity.
-- ================================================================


-- ----------------------------------------------------------------
-- 1. Approval workload distribution
-- Run before and after creating new forms to verify round-robin
-- seeding spreads work evenly across employees in the same role.
-- Change role_id to check other roles: 2=Supervisor, 4=Dept Head,
-- 5=Checker, 6=Final Approver
-- ----------------------------------------------------------------
SELECT 
    e.full_name,
    e.role_id,
    COUNT(a.id) AS total_assigned,
    SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) AS rejected
FROM employees e
LEFT JOIN approvals a ON a.approver_id = e.id
WHERE e.role_id = 2
GROUP BY e.id, e.full_name, e.role_id
ORDER BY e.role_id, total_assigned DESC;

-- ----------------------------------------------------------------
-- 2. Admin-approved forms status check
-- Verifies actAsApprover() sets statuses the pipeline recognises.
-- Should return NO rows once Fix 1 is applied.
-- ----------------------------------------------------------------
SELECT id, form_type, status, submitted_by, created_at
FROM forms
WHERE status IN ('approved', 'in_approval')
ORDER BY created_at DESC;

-- ----------------------------------------------------------------
-- 3. Stuck forms — pending approvals with no active approver
-- Catches orphaned approvals caused by deleted employees.
-- Should return NO rows in a healthy system.
-- ----------------------------------------------------------------
SELECT
    f.id AS form_id,
    f.form_type,
    f.status AS form_status,
    a.sequence,
    a.approver_id,
    e.full_name AS approver_name,
    e.is_active
FROM approvals a
JOIN forms f ON f.id = a.form_id
LEFT JOIN employees e ON e.id = a.approver_id
WHERE a.status = 'pending'
    AND (e.id IS NULL OR e.is_active = 0)
ORDER BY f.created_at DESC;

-- ----------------------------------------------------------------
-- 4. Pipeline integrity check
-- Forms whose status does not match their latest approved step.
-- Helps catch any manual DB edits or admin-path inconsistencies.
-- ----------------------------------------------------------------
SELECT
    f.id,
    f.form_type,
    f.status AS form_status,
    MAX(a.sequence) AS last_approved_seq,
    COUNT(CASE WHEN a.status = 'pending' THEN 1 END) AS pending_steps
FROM forms f
LEFT JOIN approvals a ON a.form_id = f.id
WHERE f.status NOT IN ('draft', 'rejected', 'completed')
GROUP BY f.id, f.form_type, f.status
ORDER BY f.id DESC;

-- ----------------------------------------------------------------
-- 5. Recent audit log — last 20 actions
-- Quick sanity check on who did what and when.
-- ----------------------------------------------------------------
SELECT
    al.id,
    e.full_name AS performed_by,
    al.action,
    al.entity_type,
    al.entity_id,
    al.performed_at
FROM audit_logs al
LEFT JOIN employees e ON e.id = al.performed_by
ORDER BY al.performed_at DESC
LIMIT 20;