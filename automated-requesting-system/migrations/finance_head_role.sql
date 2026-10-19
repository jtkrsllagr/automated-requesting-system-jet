-- ============================================================
-- FINANCE HEAD / EVALUATION APPROVER ROLE
-- Run this against whichever database is showing only 8 roles (missing
-- id 8 = EvaluationApprover). Your other, already-migrated database
-- confirms this is the correct name/id to use -- this just brings a
-- lagging database instance in line with it.
--
-- Safe to run even if the row already exists (no-ops via WHERE NOT EXISTS).
-- Run once via phpMyAdmin or:
--   mysql -u root -p arsdb < migrations/finance_head_role.sql
-- ============================================================

INSERT INTO roles (id, name, description)
SELECT 8, 'EvaluationApprover',
       'Finance Head. Signs Advance Payment, Request for Payment, Reimbursement, and Liquidation at the Evaluation Approval stage.'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE id = 8);