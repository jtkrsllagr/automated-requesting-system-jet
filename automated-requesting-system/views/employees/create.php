<div class="page-header">
    <h5>Add Employee</h5>
</div>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="<?= url('employees/create') ?>">

        <?= \App\Helpers\Csrf::field() ?>

        <div class="form-section-divider">
            <span>Employee Information</span>
        </div>
        <div class="form-grid g-2">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
            <div class="form-group">
                <label>Username <small class="text-muted">(optional)</small></label>
                <input type="text" name="username" placeholder="e.g. jdelacruz">
            </div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group">
                <label>Password</label>
                <div class="password-wrapper">
                    <input type="password" id="employee_password" name="password" required>
                    <button type="button" class="toggle-icon" data-toggle-password="employee_password" aria-label="Toggle password visibility">
                        <i data-lucide="eye" data-toggle-password-icon></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role_id" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department">
                    <option value="">-- Select --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Company</label>
                <select name="company">
                    <option value="">-- Select --</option>
                    <?php foreach ($companies as $comp): ?>
                        <option value="<?= htmlspecialchars($comp['name']) ?>"><?= htmlspecialchars($comp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Supervisor (Immediate Manager)</label>
                <select name="supervisor_id">
                    <option value="">-- None --</option>
                    <?php foreach ($supervisors as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Optional. Usually required for Staff to route approvals.</small>
            </div>
            <div class="form-group">
                <label>Second Supervisor (Dual Reporting)</label>
                <select name="supervisor_id_2">
                    <option value="">-- None --</option>
                    <?php foreach ($supervisors as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Optional. If set, approval stages requiring the immediate supervisor need sign-off from BOTH supervisors.</small>
            </div>
            <div class="form-group">
                <label>Department Head <small class="text-muted">(Admin Forms)</small></label>
                <select name="master_approver_id">
                    <option value="">-- Auto-assign (workload balanced) --</option>
                    <?php foreach ($masterApprovers as $ma): ?>
                        <option value="<?= $ma['id'] ?>"><?= htmlspecialchars($ma['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Optional. Routes Overtime, Leave, and Vehicle Request review stage.</small>
            </div>
            <div class="form-group">
                <label>Finance Head <small class="text-muted">(Finance Forms)</small></label>
                <select name="finance_head_id">
                    <option value="">-- Auto-assign (workload balanced) --</option>
                    <?php foreach ($financeHeads as $fh): ?>
                        <option value="<?= $fh['id'] ?>"><?= htmlspecialchars($fh['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Optional. Signs Advance Payment, Request for Payment, Reimbursement, and Liquidation at the Evaluation stage.</small>
            </div>
            <div class="form-group">
                <label>HR Verifier <small class="text-muted">(Reimbursement / Liquidation)</small></label>
                <select name="hr_verifier_id">
                    <option value="">-- Auto-assign (workload balanced) --</option>
                    <?php foreach ($hrVerifiers as $hv): ?>
                        <option value="<?= $hv['id'] ?>"><?= htmlspecialchars($hv['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Optional. Routes Reimbursement and Liquidation process approval stage.</small>
            </div>
        </div>

        <div class="form-section-divider">
            <span>Company Information</span>
        </div>
        <div class="form-grid g-2">
            <div class="form-group">
                <label>Job Title</label>
                <input type="text" name="job_title" placeholder="e.g. Title/Position">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="e.g. 09xxxxxxxxx">
            </div>
            <div class="form-group">
                <label>Date Hired</label>
                <input type="date" name="date_hired">
            </div>
            <div class="form-group">
                <label>Employment Type</label>
                <select name="employment_type">
                    <option value="full_time">Full-time</option>
                    <option value="part_time">Part-time</option>
                    <option value="contractual">Contractual</option>
                    <option value="probationary">Probationary</option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="ti ti-send"></i> Submit for Approval
            </button>
            <a href="<?= url('employees') ?>" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>
