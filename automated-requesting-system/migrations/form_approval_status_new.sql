-- Migration: update form_approval_status view with multilevel approval tracking
-- Created: 2026-05-05
CREATE VIEW form_approval_status_new AS
SELECT
    f.id AS form_id,
    f.form_type,
    f.status AS form_status,

    e.id AS submitter_id,
    e.full_name AS submitted_by,
    e.department AS submitter_department,
    f.created_at AS submitted_at,

    COUNT(a.id) AS total_steps,
    COUNT(CASE WHEN a.status = 'approved' THEN 1 END) AS approved_steps,
    COUNT(CASE WHEN a.status = 'rejected' THEN 1 END) AS rejected_steps,
    COUNT(CASE WHEN a.status = 'pending' THEN 1 END) AS pending_steps,

    CASE
        WHEN COUNT(a.id) = 0 THEN 0
        ELSE ROUND(
            COUNT(CASE WHEN a.status = 'approved' THEN 1 END) * 100.0 / COUNT(a.id), 1
        )
    END AS completion_pct,

    MIN(CASE WHEN a.status = 'pending' THEN a.sequence END) AS current_step_sequence, (
        SELECT ap2.full_name
        FROM approvals a2
        JOIN employees ap2 ON ap2.id = a2.approver_id
        WHERE a2.form_id = f.id AND a2.status = 'pending'
        ORDER BY a2.sequence LIMIT 1
    ) AS current_approver_name, (
        SELECT ap2.email
        FROM approvals a2
        JOIN employees ap2 ON ap2.id = a2.approver_id
        WHERE a2.form_id = f.id AND a2.status = 'pending'
        ORDER BY a2.sequence LIMIT 1
    ) AS current_approver_email, (
        SELECT a3.status
        FROM approvals a3
        WHERE a3.form_id = f.id AND a3.status <> 'pending'
        ORDER BY a3.sequence DESC LIMIT 1
    ) AS last_action, (
        SELECT a3.updated_at
        FROM approvals a3
        WHERE a3.form_id = f.id AND a3.status <> 'pending'
        ORDER BY a3.sequence DESC LIMIT 1
    ) AS last_action_at, (
        SELECT DATEDIFF(NOW(), a4.created_at)
        FROM approvals a4
        WHERE a4.form_id = f.id AND a4.status = 'pending'
        ORDER BY a4.sequence LIMIT 1
    ) AS days_pending_at_current_step,

    GROUP_CONCAT(
        CONCAT(a.approver_id, ':', ap.full_name, ':', a.status)
        ORDER BY a.sequence
        SEPARATOR ' → '
    ) AS approval_chain,

    CASE
        WHEN MAX(CASE WHEN a.status = 'pending' THEN DATEDIFF(NOW(), a.created_at) END) > 3
            THEN 'overdue'
        WHEN f.status = 'approved' THEN 'complete'
        WHEN f.status = 'rejected' THEN 'rejected'
        ELSE 'on_track'
    END AS sla_status

FROM forms f
JOIN employees e ON e.id  = f.submitted_by
LEFT JOIN approvals a ON a.form_id = f.id
LEFT JOIN employees ap ON ap.id = a.approver_id

GROUP BY
    f.id, f.form_type, f.status,
    e.id, e.full_name, e.department,
    f.created_at;


