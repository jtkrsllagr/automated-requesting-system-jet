-- ============================================================
-- docker/initdb/01_init.sql
-- All SQL inlined — no SOURCE commands (they don't work in
-- docker-entrypoint-initdb.d because the app container's
-- filesystem is not accessible from the db container).
-- MariaDB runs this automatically on first `docker compose up`
-- only when the db_data volume is empty / freshly created.
-- DOCKER mysql access `docker compose exec db mariadb -u ars_user -pars_pass arsdb`
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS chat_reactions, chat_typing, chat_blocked_users, chat_messages, audit_logs, approvals, forms, employees, roles, password_reset_tokens;
DROP VIEW IF EXISTS form_approval_status, form_approval_status_new;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- ROLES
-- ============================================================
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT
);

INSERT INTO roles (name, description) VALUES
('SysAdmin', 'Full system access'),
('ImmediateHead', 'Can approve/reject forms'),
('Staff', 'Can submit forms only'),
('MasterApprover', 'Master-level approval authority'),
('AcquisitionChecker', 'Finance/Accounting Checker'),
('FinalApprover', 'Final sign-off authority'),
('AdminApprover', 'Combined authority: Immediate Supervisor, Department Head, and Final Approver approval stages'),
('EvaluationApprover', 'Finance Head. Signs Advance Payment, Request for Payment, Reimbursement, and Liquidation at the Evaluation Approval stage.'),
('HRVerifier', 'Cross-checks employee attendance records and co-signs the Process Approval stage on Reimbursement and Liquidation forms');

-- ============================================================
-- EMPLOYEES
-- ============================================================
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(20) UNIQUE NOT NULL,
    avatar VARCHAR(255) NOT NULL DEFAULT('default.png'), -- avatar = 'default.png' WHERE avatar = '' OR avatar IS NULL,
    username VARCHAR(50) UNIQUE NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    job_title VARCHAR(100) NULL,
    phone VARCHAR(30) NULL,
    date_hired DATE NULL,
    employment_type ENUM('full_time', 'part_time', 'contractual', 'probationary') NOT NULL DEFAULT 'full_time',
    supervisor_id INT NULL,
    supervisor_id_2 INT NULL,
    master_approver_id INT NULL,
    hr_verifier_id INT NULL,
    finance_head_id INT NULL,
    department VARCHAR(100) NULL,
    company VARCHAR(150) NULL,
    is_active TINYINT(1) DEFAULT 1,
    employment_status ENUM('employed','resigned','floating') NOT NULL DEFAULT 'employed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    FOREIGN KEY (supervisor_id) REFERENCES employees(id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_supervisor_2 FOREIGN KEY (supervisor_id_2) REFERENCES employees(id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_master_approver FOREIGN KEY (master_approver_id) REFERENCES employees(id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_hr_verifier FOREIGN KEY (hr_verifier_id) REFERENCES employees(id) ON DELETE SET NULL,
    CONSTRAINT fk_employees_finance_head FOREIGN KEY (finance_head_id) REFERENCES employees(id) ON DELETE SET NULL
);

CREATE INDEX idx_employees_username ON employees(username);
CREATE INDEX idx_employees_email ON employees(email);
CREATE INDEX idx_employees_role  ON employees(role_id);

CREATE INDEX idx_employee_supervisor_2 ON employees(supervisor_id_2);
CREATE INDEX idx_employees_master_approver ON employees(master_approver_id);
CREATE INDEX idx_employees_hr_verifier ON employees(hr_verifier_id);
CREATE INDEX idx_employees_finance_head ON employees(finance_head_id);

INSERT INTO employees (employee_code, full_name, email, password_hash, role_id, department) VALUES
('EMP-0001', 'System Admin', 'it@3ehitech.com', '$2y$10$/MGzDUKgAfdRp8EkWJRdh.29vC2Wjc1c4YDymPxvuajawzs3U2bc6', 1, 'IT');

-- ============================================================
-- DEPARTMENTS
-- ============================================================
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- COMPANIES (mirrors departments table)
-- ============================================================
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) UNIQUE NOT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- FORMS
-- ============================================================
CREATE TABLE forms (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    form_type ENUM(
        'advance_payment',
        'overtime_authorization',
        'request_for_payment',
        'leave_application',
        'reimbursement',
        'liquidation',
        'vehicle_request'
    ) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft'
        CHECK (status IN (
            'draft', 'submitted',
            'immediatehead_approved',
            'hr_verified',
            'department_reviewed',
            'process_approved',
            'finance_reviewed',
            'final_approved',
            'completed', 'rejected', 'cancelled'
        )),
    submitted_by INT NOT NULL,
    data JSON NULL,
    check_voucher VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submitted_by) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE INDEX idx_forms_type ON forms(form_type);
CREATE INDEX idx_forms_status ON forms(status);
CREATE INDEX idx_forms_submitted ON forms(submitted_by);

-- ============================================================
-- APPROVALS
-- ============================================================
CREATE TABLE approvals (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    form_id BIGINT NOT NULL,
    approver_id INT NOT NULL,
    sequence SMALLINT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','approved','rejected','skipped')),
    remarks TEXT,
    file_path VARCHAR(500) NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES employees(id) ON DELETE RESTRICT,
    CONSTRAINT uk_approval_form_seq_approver UNIQUE (form_id, sequence, approver_id)
);

CREATE INDEX idx_approvals_form ON approvals(form_id);
CREATE INDEX idx_approvals_approver ON approvals(approver_id);
CREATE INDEX idx_approvals_status ON approvals(status);

-- ============================================================
-- PASSWORD RESET TOKENS
-- ============================================================
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

CREATE INDEX idx_prt_token ON password_reset_tokens(token);
CREATE INDEX idx_prt_employee ON password_reset_tokens(employee_id);

-- ============================================================
-- AUDIT LOGS
-- ============================================================
CREATE TABLE audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    performed_by INT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(20) NOT NULL,
    entity_id BIGINT NOT NULL,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (performed_by) REFERENCES employees(id) ON DELETE SET NULL
);

CREATE INDEX idx_audit_performed_by ON audit_logs(performed_by);
CREATE INDEX idx_audit_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX idx_audit_performed_at ON audit_logs(performed_at);

-- ============================================================
-- SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NOT NULL DEFAULT '',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('app_name', 'AutomatedRequestingSystem'),
    ('app_url', 'https://localhost'),
    ('app_env', 'local'),
    ('app_timezone', 'Asia/Manila'),
    ('items_per_page', '20'),
    ('session_lifetime', '120'),
    ('mail_host', 'smtp.gmail.com'),
    ('mail_port', '587'),
    ('mail_encryption', 'tls'),
    ('mail_username', ''),
    ('mail_password', ''),
    ('mail_from_address', ''),
    ('mail_from_name', 'Automated Requesting System'),
    ('theme_color', 'blue'),
    ('theme_mode', 'light'),
    ('sidebar_collapsed', '0'),
    ('notify_on_submit', '1'),
    ('notify_on_approval', '1'),
    ('notify_on_rejection','1'),
    ('notify_on_completion', '1'),
    ('upload_path', 'public/uploads'),
    ('max_file_size_mb', '10'),
    ('allowed_file_types', 'pdf,jpg,png,docx');

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    form_id INT UNSIGNED NULL,
    type VARCHAR(40) NOT NULL DEFAULT 'info',
    message VARCHAR(500) NOT NULL,
    link VARCHAR(500) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notifications_user (user_id, is_read, created_at),
    KEY idx_notifications_form (form_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- USER SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS user_settings (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_key (user_id, `key`),
    CONSTRAINT fk_us_user FOREIGN KEY (user_id) REFERENCES employees(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VIEW: form_approval_status
-- ============================================================
CREATE VIEW form_approval_status AS
SELECT
    f.id AS form_id,
    f.form_type,
    f.status AS form_status,
    e.full_name AS submitted_by,
    f.created_at,
    COUNT(a.id) AS total_steps,
    COUNT(CASE WHEN a.status = 'approved' THEN 1 END) AS approved_steps,
    MIN(CASE WHEN a.status = 'pending' THEN a.sequence END) AS next_pending_sequence,
    GROUP_CONCAT(a.approver_id ORDER BY a.sequence SEPARATOR ',') AS approver_chain
FROM forms f
JOIN employees e ON e.id = f.submitted_by
LEFT JOIN approvals a ON a.form_id = f.id
GROUP BY f.id, f.form_type, f.status, e.full_name, f.created_at;

-- ============================================================
-- VIEW: form_approval_status_new
-- ============================================================
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
        ELSE ROUND(COUNT(CASE WHEN a.status = 'approved' THEN 1 END) * 100.0 / COUNT(a.id), 1)
    END AS completion_pct,
    MIN(CASE WHEN a.status = 'pending' THEN a.sequence END) AS current_step_sequence,
    (SELECT ap2.full_name FROM approvals a2 JOIN employees ap2 ON ap2.id = a2.approver_id
     WHERE a2.form_id = f.id AND a2.status = 'pending' ORDER BY a2.sequence LIMIT 1) AS current_approver_name,
    (SELECT ap2.email FROM approvals a2 JOIN employees ap2 ON ap2.id = a2.approver_id
     WHERE a2.form_id = f.id AND a2.status = 'pending' ORDER BY a2.sequence LIMIT 1) AS current_approver_email,
    (SELECT a3.status FROM approvals a3
     WHERE a3.form_id = f.id AND a3.status <> 'pending' ORDER BY a3.sequence DESC LIMIT 1) AS last_action,
    (SELECT a3.updated_at FROM approvals a3
     WHERE a3.form_id = f.id AND a3.status <> 'pending' ORDER BY a3.sequence DESC LIMIT 1) AS last_action_at,
    (SELECT DATEDIFF(NOW(), a4.assigned_at) FROM approvals a4
     WHERE a4.form_id = f.id AND a4.status = 'pending' ORDER BY a4.sequence LIMIT 1) AS days_pending_at_current_step,
    GROUP_CONCAT(
        CONCAT(a.approver_id, ':', ap.full_name, ':', a.status)
        ORDER BY a.sequence SEPARATOR ' → '
    ) AS approval_chain,
    CASE
        WHEN MAX(CASE WHEN a.status = 'pending' THEN DATEDIFF(NOW(), a.assigned_at) END) > 3 THEN 'overdue'
        WHEN f.status = 'approved' THEN 'complete'
        WHEN f.status = 'rejected' THEN 'rejected'
        ELSE 'on_track'
    END AS sla_status
FROM forms f
JOIN employees e  ON e.id  = f.submitted_by
LEFT JOIN approvals a  ON a.form_id = f.id
LEFT JOIN employees ap ON ap.id = a.approver_id
GROUP BY f.id, f.form_type, f.status, e.id, e.full_name, e.department, f.created_at;

-- ============================================================
-- ARS Chat Integration — ARS database
-- ============================================================
CREATE TABLE IF NOT EXISTS chat_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text', 'sticker', 'attachment', 'form_share') NOT NULL DEFAULT 'text',
    attachment_url VARCHAR(500) NULL DEFAULT NULL,
    form_id BIGINT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cm_sender (sender_id),
    INDEX idx_cm_receiver (receiver_id),
    INDEX idx_sent_at (sent_at),
    INDEX idx_cm_form (form_id),
    CONSTRAINT fk_cm_sender FOREIGN KEY (sender_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_receiver FOREIGN KEY (receiver_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_blocked_users (
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (blocker_id, blocked_id),
    CONSTRAINT fk_cbu_blocker FOREIGN KEY (blocker_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_cbu_blocked FOREIGN KEY (blocked_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_typing (
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (sender_id, receiver_id),
    INDEX idx_typing_receiver (receiver_id),
    CONSTRAINT fk_typing_sender FOREIGN KEY (sender_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_typing_receiver FOREIGN KEY (receiver_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_reactions (
    message_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id, user_id),
    CONSTRAINT fk_cr_message FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_cr_user FOREIGN KEY (user_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE employees
    ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL DEFAULT NULL;
 
ALTER TABLE employees
    ADD INDEX IF NOT EXISTS idx_employees_last_seen (last_seen_at);