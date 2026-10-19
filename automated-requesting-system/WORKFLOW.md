### System Flow of ARS
Documentation of ARS

### Administrative Forms
- **Overtime Authorization**
- **Leave Application**
- **Vehicle Request**

### Finance Forms
- **Advance Payment**
- **Request for Payment**
- **Reimbursement**
- **Liquidation**

### Stages for Administrative Forms
***Draft (Preview)*** -> ***Submitted (Employee Form Request)*** -> ***Checker (Immediate Head Checking)*** -> ***Evaluation (Dept. Head Checking)*** -> ***Final (Management Approval)***

> **Leave Application & Overtime Authorization for Management, Human Resources/HR, or Admin departments:** after submission, the request goes directly to Final Approver. Final approval completes the request and records the Human Resources Recordkeeping stage as complete. Existing requests still awaiting the old intermediate stages are normalized when the Final Approver queue is opened.

> **Leave Application & Overtime Authorization for Accounting, Information Technology (IT), Finance, Logistics, Sales, Pre-Sales (Engineer), or Post-Sales (Engineer) departments:** Request Submitted → Supervisor Verification → Final Authorization → Human Resources Recordkeeping. Final approval completes the request; Human Resources Recordkeeping is recorded as complete in the approval trail.

### Stages for Finance Forms
***Draft (Preview)*** -> ***Submitted (Employee Form Request)*** -> ***Checker (Immediate Head Checking)*** -> ***Process (Accounting Checking)*** -> ***Evaluation (Finance Head Checking)*** -> ***Final Approval (Approved & Completed)***

> **Reimbursement & Liquidation only:** at the Process (Accounting Checking) stage, HR co-signs alongside Accounting — both must sign off before the form moves on to Evaluation. Advance Payment and Request for Payment go through Accounting alone at that stage.

### Created Account Details
```bash
    cd C:\xampp\mysql\bin
    mysql -u root -p
    USE arsdb;
    SELECT id, full_name, email, role_id FROM employees ORDER BY role_id;
```

```
    Role map (align with the employees table):
        1 = SysAdmin
        2 = Immediate Head / Supervisor (ImmediateHead)
        3 = Regular Employee (Staff)
        4 = Department Head / Master Approver (MasterApprover)
        5 = Accounting / Process Approval (AcquisitionChecker)
        6 = Final Approver (FinalApprover)
        7 = Procurement / Admin Approver (AdminApprover)
        8 = Finance Head / Evaluation Approver (EvaluationApprover)
        9 = HR Verifier (HRVerifier)
```

### Admin Forms
| Step | Stage                          | Login Account          | Email                   |
|------|---------------------------------|-------------------------|--------------------------|
| 1    | Requestor — submits the form   | Requestor                | staff@email.com          |
| 2    | Checker (Immediate Head)       | Immediate Supervisor     | approver@email.com       |
| 3    | Evaluation (Dept. Head)        | Department Head          | head@gmail.com           |
| 4    | Final (Management Approval)    | Final Approver            | finalapprover@email.com  |
| 5    | Completion of Approval         | System Admin              | sysadmin@email.com       |

### Finance Forms
| Step | Stage                                   | Login Account                        | Email                    |
|------|-------------------------------------------|----------------------------------------|----------------------------|
| 1    | Requestor — submits the form              | Requestor                               | staff@email.com            |
| 2    | Checker (Immediate Head)                  | Immediate Supervisor                    | approver@email.com         |
| 3    | Process (Accounting Checking)             | Accounting                              | finance@email.com          |
| 4    | Evaluation (Finance Head Checking)        | Finance Head                            | financehead@email.com      |
| 5    | Final Approval                            | Final Approver                          | finalapprover@email.com    |
| 6    | Completion of Approval                    | System Admin                            | sysadmin@email.com         |

### Role Map

| role_id | Role                                            | Description                                                                                    |
|---------|--------------------------------------------------|--------------------------------------------------------------------------------------------------|
| 1       | IT (SysAdmin)                                    | Full system access. Can act on behalf of any stage; marks Finance forms completed after final approval. |
| 2       | Approver/Immediate Supervisor (ImmediateHead)     | First-level checker. Reviews and approves submitted requests from their direct reports.          |
| 3       | Regular Employee (Staff)                          | Submits forms/requests. Can only view their own submissions.                                     |
| 4       | Department Head / Master Approver (MasterApprover)| Primary approver for the Evaluation (Dept. Head Checking) stage on Administrative forms. Can also stand in as Checker (Immediate Head) on **any** form type. |
| 5       | Accounting / Process Approval (AcquisitionChecker)| Handles the Process (Accounting Checking) stage on Finance forms; co-signs alongside HR on Reimbursement/Liquidation, and can also stand in for HR's row on that stage. |
| 6       | Management / Final Approver (FinalApprover)       | Last approval authority. Grants final approval on all form types. Shared queue — see below.       |
| 7       | Procurement Head / Admin Approver (AdminApprover) | Stands in for other roles depending on form type: Vehicle Request (Checker, Dept. Head, Final); Leave Application & Overtime (Dept. Head only). |
| 8       | Finance Head (EvaluationApprover)                 | Handles the Evaluation (Finance Head Checking) stage on Finance forms. Shared queue — see below.  |
| 9       | HR Verifier (HRVerifier)                          | Co-signs the Process (Accounting Checking) stage on Reimbursement and Liquidation forms, cross-checking attendance records. Shared queue — see below. |

### Approval Delegation & Shared Queues

Several roles are no longer "one specific person only" — any account holding that role can see and act on the relevant pending requests, and whoever acts first completes the stage (the request is then attributed to whoever actually acted, not just whoever was auto-assigned when the form was submitted):

- **Final Approver (role 6)** — every Final Approver account sees every pending Final Approval request, on every form type.
- **HR Verifier (role 9)** — every HR Verifier account sees every pending HR co-sign request (Reimbursement/Liquidation only).
- **Finance Head (role 8)** — every Finance Head account sees every pending Evaluation Approval request.
- **Master Approver (role 4)** — in addition to their normal Evaluation (Dept. Head) stage on Admin forms, can also act as Checker (Immediate Head) on any form type.
- **Admin Approver (role 7)** — can stand in for other stages depending on form type (see Role Map above), but is never directly assigned as the "default" approver for those stages.

Once a Final Approver, HR Verifier, or Finance Head account acts on a request — even one they weren't personally assigned — they retain the ability to view it afterward (including in the Approval History tab), whether it was approved, rejected, or later completed by someone else.

Note: an employee can also have a **specific** Master Approver, HR Verifier, or Finance Head assigned to them individually (`master_approver_id`, `hr_verifier_id`, `finance_head_id` on the `employees` table, set from the Add/Edit Employee screen) — when set, that specific person is tried first before falling back to whoever on that role has the lightest current workload.