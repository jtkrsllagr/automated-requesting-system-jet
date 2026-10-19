# Automated Requesting System (ARS)

A paperless, web-based request-and-approval platform built for small and medium enterprises (SMEs). ARS digitizes common HR, administrative, and finance paperwork — leave, overtime, vehicle use, cash advances, reimbursements, liquidations, and payment requests — and routes each request through a **multi-level, role-based approval pipeline**, with an audit trail, notifications, an internal chat, and a small set of everyday office tools built in.

For the complete, illustrated walkthrough of every role and feature, see **`ARS_User_Manual.pdf`** in this package. This README is the quick-reference / technical overview.

---

## 1. Core Features

| Feature | Description |
|---|---|
| **Advance Payment Form** | Request an advance/cash payment for anticipated expenses. |
| **Overtime Authorization Form** | Request and document approval for overtime work. |
| **Request for Payment Form** | Submit a payment request for services, purchases, or obligations. |
| **Leave Application Form** | Apply for vacation, sick, parental, or other leave; tracks leave credits. |
| **Reimbursement Form** | Claim repayment for business expenses already paid out of pocket. |
| **Liquidation Form** | Reconcile/settle expenses against a previously issued cash advance. |
| **Vehicle Request Form** | Request a company vehicle, with trip schedule and destination. |
| **Multi-level Approval Engine** | Every form moves through a defined chain of checkers/approvers with approve/reject actions, remarks, and a visible pipeline/status trail. |
| **Approval Inbox** | A personal "pending action" queue for anyone who holds an approver role. |
| **My Submissions / All Requests** | Employees track their own filed forms; SysAdmin can see and export every request system-wide (CSV). |
| **Notifications** | In-app bell notifications for status changes, new approvals needed, and messages. |
| **Internal Chat** | Direct messaging between employees — file/image sharing, message reactions, read receipts, typing indicators, block/unblock, and the ability to share a form directly into a chat. |
| **Employee Management** | SysAdmin can create, edit, deactivate, and manage login access for every employee, including their role, department, company, and specific approver assignments. |
| **Department & Company Management** | SysAdmin maintains the org's departments and (for multi-entity setups) companies/branches, each with their own members and branding (company logo). |
| **Tools Suite** | World clock, calculator, height/weight converter, personal sticky notes, and a payslip request shortcut — all under one "Tools" page. |
| **Settings** | General app settings, mail (SMTP) configuration, appearance/theme, notification preferences, and storage — split between admin-level and per-user settings. |
| **Profile & Avatar** | Every user manages their own profile photo and account details. |

## 2. Roles & Role IDs

ARS uses a single `role_id` per employee account to control what they can see and do.

| ID | Role | Summary |
|----|------|---------|
| 1 | **SysAdmin (IT)** | Full system access. Can act on behalf of any approval stage, manages employees/departments/companies/settings, and marks Finance forms completed after final approval. |
| 2 | **Immediate Head / Supervisor** | First-level checker — reviews and approves requests from their direct reports. |
| 3 | **Regular Employee (Staff)** | Submits requests; can only view their own submissions. |
| 4 | **Department Head / Master Approver** | Handles the Evaluation (Dept. Head) stage on Administrative forms; can also stand in as Checker on **any** form type. |
| 5 | **Accounting / Process Approval** | Handles the Process (Accounting Checking) stage on Finance forms; co-signs Reimbursement/Liquidation alongside HR. |
| 6 | **Management / Final Approver** | Last approval authority on every form type. Shared queue — any Final Approver account can act. |
| 7 | **Procurement / Admin Approver** | Stands in for other roles depending on form type (see manual for the full coverage table). |
| 8 | **Finance Head / Evaluation Approver** | Handles the Evaluation (Finance Head Checking) stage on Finance forms. Shared queue. |
| 9 | **HR Verifier** | Co-signs the Process stage on Reimbursement/Liquidation forms, cross-checking attendance records. Shared queue. |

> Roles 4, 6, 8, and 9 use **shared queues** — every account holding that role sees all pending requests for that stage, and whoever acts first completes it. An employee can also be assigned a **specific** Master Approver / HR Verifier / Finance Head individually; that person is tried first before falling back to the lightest-workload holder of the role.

## 3. Approval Pipelines

**Administrative forms** (Overtime, Leave, Vehicle Request):
```
Draft → Submitted → Checker (Immediate Head) → Evaluation (Dept. Head) → Final (Management) → Completed
```

**Finance forms** (Advance Payment, Request for Payment):
```
Draft → Submitted → Checker (Immediate Head) → Process (Accounting) → Evaluation (Finance Head) → Final (Management) → Completed
```

**Reimbursement & Liquidation** (Finance forms with an HR co-sign):
```
Draft → Submitted → Checker (Immediate Head) → Process (Accounting + HR co-sign) → Evaluation (Finance Head) → Final (Management) → Completed
```

At every stage the approver can **Approve** (moves to the next stage) or **Reject** (with a required remark, returned to the requester). The **SysAdmin** marks a fully Final-Approved Finance form as *Completed* once payment/processing is done.

## 4. Tech Stack

- **Language:** PHP (custom lightweight MVC — no framework)
- **Database:** MySQL / MariaDB
- **Front end:** Server-rendered PHP views, Bootstrap 5, vanilla JS
- **Libraries:** Composer, PHPMailer (email/notifications), vlucas/phpdotenv (`.env` config)
- **Containerization:** Docker + docker-compose (see `DOCKER.md`)

## 5. Project Structure

```
automated-requesting-system/
├── app/
│   ├── Controllers/     # AuthController, FormController, ApprovalController,
│   │                     EmployeeController, DepartmentController, CompanyController,
│   │                     NotificationController, SettingsController, ToolsController, ChatController
│   ├── Middleware/      # AuthMiddleware (login gate), RoleMiddleware (role gate)
│   ├── Model/           # Approval.php
│   ├── Helpers/         # csrf.php, EmployeeCode.php, FormLabels.php
│   └── Services/        # NotificationService.php
├── config/              # app.php, database.php, arsdb.sql (base schema)
├── migrations/          # Incremental schema changes (chat, notes, settings, roles, etc.)
├── public/              # Web root: index.php entry point, scripts, stylesheets, uploads
├── routes/web.php        # All application routes
├── views/                # One folder per module (forms, approvals, employees, chat, tools, settings...)
├── docker/               # Dockerfile, Apache config, certs, init DB scripts
└── README.md
```

## 6. Getting Started (Local / XAMPP)

1. Install PHP, MySQL (or MariaDB), and Composer.
2. Run `composer install`.
3. Create a `.env` file (see `notes/NOTES.txt` for a sample) with `APP_URL`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and mail settings.
4. Import `config/arsdb.sql` into your database, then apply any files in `migrations/` that aren't already included.
5. Point your web server's document root at `public/`.
6. Visit the app URL and log in with an account provisioned by your SysAdmin.

For a Dockerized setup, see `DOCKER.md`. For a step-by-step guide to accessing a company-hosted instance over the local network, see `INSTRUCTIONS.md`.

## 7. Further Reading in This Package

- **`ARS_User_Manual.pdf`** — full, detailed manual: every role's dashboard and permissions, step-by-step instructions for filing and approving each form type, the chat and tools features, employee/department/company administration, settings, and troubleshooting.
- `WORKFLOW.md` — the underlying approval-stage/role reference this manual is built from.
- `INSTRUCTIONS.md` — network access instructions for testers on the company LAN.
- `DOCKER.md` — Docker deployment instructions.
