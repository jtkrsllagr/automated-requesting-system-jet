<div class="page-header">
    <div class="page-title-group">
        <h5 class="page-heading">Edit Employee</h5>
        <p class="page-subheading">Update profile and system permissions for <?= htmlspecialchars($employee['full_name']) ?></p>
    </div>
    <a href="<?= url('employees') ?>" class="btn btn-ghost">
        <i class="ti ti-arrow-left"></i> Back to List
    </a>
</div>

<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="<?= url('employees/update/' . $employee['id']) ?>">
        <?= \App\Helpers\Csrf::field() ?>

        <div class="form-grid g-2">
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?= htmlspecialchars($employee['full_name']) ?>" required></div>
            <div class="form-group">
                <label>Username <span class="muted">(optional, used for login)</span></label>
                <input type="text" name="username" value="<?= htmlspecialchars($employee['username'] ?? '') ?>" placeholder="e.g. juan">
            </div>
            <div class="form-group"><label>Email Address</label><input type="email" name="email" value="<?= htmlspecialchars($employee['email']) ?>" required></div>
            <div class="form-group">
                <label>New Password <span class="muted">(Leave blank to keep current)</span></label>
                <input type="password" name="password" placeholder="••••••••">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role_id" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>" <?= $employee['role_id'] == $role['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department">
                    <option value="">-- Select --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['name']) ?>"
                            <?= $employee['department'] === $dept['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Company</label>
                <select name="company">
                    <option value="">-- Select --</option>
                    <?php foreach ($companies as $comp): ?>
                        <option value="<?= htmlspecialchars($comp['name']) ?>"
                            <?= $employee['company'] === $comp['name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($comp['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Supervisor (Immediate Manager)</label>
                <select name="supervisor_id">
                    <option value="">-- None --</option>
                    <?php foreach ($supervisors as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $employee['supervisor_id'] == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Second Supervisor (Dual Reporting)</label>
                <select name="supervisor_id_2">
                    <option value="">-- None --</option>
                    <?php foreach ($supervisors as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $employee['supervisor_id_2'] == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Optional. If set, approval stages requiring the immediate supervisor need sign-off from BOTH supervisors.</small>
            </div>
            <div class="form-group">
                <label>Department Head <small class="text-muted">(Admin Forms)</small></label>
                <select name="master_approver_id">
                    <option value="">-- Auto-assign (workload balanced) --</option>
                    <?php foreach ($masterApprovers as $ma): ?>
                        <option value="<?= $ma['id'] ?>" <?= $employee['master_approver_id'] == $ma['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ma['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Optional. Routes Overtime, Leave, and Vehicle Request review stage.</small>
            </div>
            <div class="form-group">
                <label>Finance Head <small class="text-muted">(Finance Forms)</small></label>
                <select name="finance_head_id">
                    <option value="">-- Auto-assign (workload balanced) --</option>
                    <?php foreach ($financeHeads as $fh): ?>
                        <option value="<?= $fh['id'] ?>" <?= $employee['finance_head_id'] == $fh['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($fh['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Optional. Signs Advance Payment, Request for Payment, Reimbursement, and Liquidation at the Evaluation stage.</small>
            </div>
            <div class="form-group">
                <label>HR Verifier <small class="text-muted">(Reimbursement / Liquidation)</small></label>
                <select name="hr_verifier_id">
                    <option value="">-- Auto-assign (workload balanced) --</option>
                    <?php foreach ($hrVerifiers as $hv): ?>
                        <option value="<?= $hv['id'] ?>" <?= (int)$employee['hr_verifier_id'] === (int)$hv['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($hv['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Optional. Routes Reimbursement and Liquidation process approval stage.</small>
            </div>
            <div class="form-group g-span-2">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" value="1" <?= $employee['is_active'] ? 'checked' : '' ?>>
                    Account is active and can login
                </label>
            </div>
        </div>

        <div class="form-section-divider">
            <span>Company Information</span>
        </div>
        <div class="form-grid g-2">
            <div class="form-group">
                <label>Job Title</label>
                <input type="text" name="job_title"
                    value="<?= htmlspecialchars($employee['job_title'] ?? '') ?>"
                    placeholder="e.g. Title/Position">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone"
                    value="<?= htmlspecialchars($employee['phone'] ?? '') ?>"
                    placeholder="e.g. 09xxxxxxxxx">
            </div>
            <div class="form-group">
                <label>Date Hired</label>
                <input type="date" name="date_hired"
                    value="<?= htmlspecialchars($employee['date_hired'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Employment Type</label>
                <select name="employment_type">
                    <?php foreach (['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contractual' => 'Contractual', 'probationary' => 'Probationary'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($employee['employment_type'] ?? 'full_time') === $val ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-action">
            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Save Changes</button>
            <a href="<?= url('employees') ?>" class="btn btn-ghost">Cancel</a>
        </div>
    </form>
</div>
