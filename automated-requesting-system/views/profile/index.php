<?php
    $roleLabels = [ 1 => 'SyS Admin', 2 => 'Immediate Head', 3 => 'Staff', 4 => 'Dept. Head', 5 => 'Acquisition Checker', 6 => 'Final Approver', 7 => 'Admin Approver' ];
    $roleName = $roleLabels[$employee['role_id']] ?? 'User';
    $initials = strtoupper(substr($employee['full_name'], 0, 2));
    $updatedAt = $employee['updated_at'] ? date('M d, Y', strtotime($employee['updated_at'])) : '—'
?>

<?php $avatarUrl = avatar_url($employee['avatar'] ?? null); ?>

<link rel="stylesheet" href="<?= url('stylesheets/profile.css') ?>">

<div class="page-header">
    <div class="page-title-group">
        <h5 class="page-heading">My Profile</h5>
        <p class="page-subheading">Manage your account details and login credentials.</p>
    </div>
    <span class="profile-updated-at">
        <i class="ti ti-clock"></i>
        Last updated <?= $updatedAt ?>
    </span>
</div>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="profile-layout">

    <!-- Sidebar -->
    <aside class="profile-sidebar">

        <div class="profile-avatar-wrap">
            <?php if ($avatarUrl): ?>
                <img src="<?= $avatarUrl ?>" class="profile-avatar-img" alt="Profile picture">
            <?php else: ?>
                <div class="profile-avatar"><?= $initials ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= url('profile/avatar') ?>"
                enctype="multipart/form-data" id="avatar-form">
                <?= \App\Helpers\Csrf::field() ?>
                <input type="file" name="avatar" id="avatar-input"
                    accept="image/jpeg, image/jpg, image/png, image/webp, image/gif"
                    class="avatar-file-input"
                >
                <button type="button" class="avatar-edit-btn" id="avatar-edit-btn" title="Change photo">
                    <i class="ti ti-camera"></i>
                </button>
            </form>
        </div>

        <div>
            <div class="profile-name"><?= htmlspecialchars($employee['full_name']) ?></div>
            <div class="profile-role"><?= $roleName ?></div>
            <div class="profile-code"><?= htmlspecialchars($employee['employee_code']) ?></div>
        </div>
        
        <?php if ($employee['is_active']): ?>
            <span class="badge-active"><i class="ti ti-circle-check"></i> Active</span>
        <?php else: ?>
            <span class="badge-inactive"><i class="ti ti-circle-x"></i> Inactive</span>
        <?php endif; ?>

        <div class="profile-divider"></div>
        <div class="profile-meta">
            <?php if (!empty($employee['company'])): ?>
                <div class="profile-meta-row"><i class="ti ti-building"></i> <?= htmlspecialchars($employee['company']) ?></div>
            <?php endif; ?>
            <?php if (!empty($employee['department'])): ?>
                <div class="profile-meta-row"><i class="ti ti-users-group"></i> <?= htmlspecialchars($employee['department']) ?></div>
            <?php endif; ?>
            <div class="profile-meta-row"><i class="ti ti-mail"></i> <?= htmlspecialchars($employee['email']) ?></div>
            <?php if (!empty($employee['supervisor_name'])): ?>
                <div class="profile-meta-row"><i class="ti ti-user"></i> <?= htmlspecialchars($employee['supervisor_name']) ?></div>
            <?php endif; ?>
        </div>
        <div class="profile-divider"></div>
        <nav class="profile-nav">
            <a href="#account" class="profile-nav-item active"><i class="ti ti-id-badge"></i> Account details</a>
            <a href="#password" class="profile-nav-item"><i class="ti ti-lock"></i> Password</a>
            <a href="#payslip" class="profile-nav-item"><i class="ti ti-file-invoice"></i> Payslip</a>
            <a href="#leave-credits" class="profile-nav-item"><i class="ti ti-calendar-stats"></i> Leave Credits</a>
        </nav>
    </aside>

    <!-- Main -->
    <div class="profile-main">

        <!-- Account Details -->
        <div class="section-card" id="account">
            <form method="POST" action="<?= url('profile') ?>">
                <?= \App\Helpers\Csrf::field() ?>
                <input type="hidden" name="section" value="account">

                <div class="section-head">
                    <div class="section-head-left"><i class="ti ti-user-circle"></i> Account Details</div>
                    <span class="section-hint">Fields marked * are required</span>
                </div>
                <div class="section-body">
                    <div class="field-grid">
                        <div class="field-group">
                            <label class="field-label">Employee code</label>
                            <input type="text" value="<?= htmlspecialchars($employee['employee_code']) ?>" disabled>
                        </div>
                        <div class="field-group">
                            <label class="field-label">Email address</label>
                            <input type="email" value="<?= htmlspecialchars($employee['email']) ?>" disabled>
                        </div>
                        <div class="field-group">
                            <label class="field-label">Full name *</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($employee['full_name']) ?>" required>
                        </div>
                        <div class="field-group">
                            <label class="field-label">
                                Username
                                <span class="field-optional">optional</span>
                            </label>
                            <div class="field-input-wrap">
                                <input type="text" name="username" value="<?= htmlspecialchars($employee['username'] ?? '') ?>" placeholder="e.g. jdelacruz">
                                <i class="ti ti-at"></i>
                            </div>
                        </div>
                        <div class="field-group">
                            <label class="field-label">Department</label>
                            <select name="department">
                                <option value="">-- Select --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['name']) ?>"
                                        <?= ($employee['department'] ?? '') === $dept['name'] ? 'selected' : '' ?> 
                                    >
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="section-footer">
                    <span class="footer-hint">
                        <i class="ti ti-info-circle"></i>
                        Email and employee code cannot be changed here.
                    </span>
                    <div class="btn-row">
                        <a href="<?= url('profile') ?>" class="btn btn-ghost">Discard</a>
                        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Save changes</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Password -->
        <div class="section-card" id="password">
            <form method="POST" action="<?= url('profile') ?>">
                <?= \App\Helpers\Csrf::field() ?>
                <input type="hidden" name="section" value="password">

                <div class="section-head">
                    <div class="section-head-left"><i class="ti ti-lock"></i> Change password</div>
                    <span class="section-hint">Leave blank to keep current</span>
                </div>
                <div class="section-body">
                    <div class="field-grid">
                        <div class="field-group">
                            <label class="field-label">Current password</label>
                            <div class="field-input-wrap">
                                <input type="password" id="current_password" name="current_password" placeholder="••••••••">
                                <button type="button" class="eye-toggle" data-target="current_password" title="Show/hide"><i class="ti ti-eye"></i></button>
                            </div>
                        </div>
                        <div class="field-group"></div>
                        <div class="field-group">
                            <label class="field-label">New password</label>
                            <div class="field-input-wrap">
                                <input type="password" id="new_password" name="new_password" placeholder="Min. 8 characters">
                                <button type="button" class="eye-toggle" data-target="new_password" title="Show/hide"><i class="ti ti-eye"></i></button>
                            </div>
                            <div class="strength-bar-wrap" id="strength-bar-wrap">
                                <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                                <span class="strength-label" id="strength-label"></span>
                            </div>
                        </div>
                        <div class="field-group">
                            <label class="field-label">Confirm new password</label>
                            <div class="field-input-wrap">
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password">
                                <button type="button" class="eye-toggle" data-target="confirm_password" title="Show/hide"><i class="ti ti-eye"></i></button>
                            </div>
                            <span class="field-hint" id="match-hint"></span>
                        </div>
                    </div>
                </div>
                <div class="section-footer">
                    <span class="footer-hint">
                        <i class="ti ti-shield-check"></i>
                        Use a strong, unique password you don't reuse elsewhere.
                    </span>
                    <div class="btn-row">
                        <button type="submit" class="btn btn-primary"><i class="ti ti-lock-check"></i> Update password</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Payslip -->
        <div class="section-card" id="payslip">
            <div class="section-head">
                <div class="section-head-left"><i class="ti ti-file-invoice"></i> Payslip</div>
                <span class="selection-hint">Your recent payslips</span>
            </div>
            <div class="section-body">
                <div class="empty-state">
                    <i class="ti ti-file-off empty-state-icon"></i> No payslips available yet. (Coming Soon)
                </div>
            </div>
        </div>

        <!-- Leave Credits -->
        <div class="section-card" id="leave-credits">
            <div class="section-head">
                <div class="section-head-left"><i class="ti ti-calendar-stats"></i> Leave Credits</div>
                <span class="secction-hint">Remaining leave balance</span>
            </div>
            <div class="section-body">
                <div class="empty-state">
                    <i class="ti ti-calendar-off empty-state-icon"></i> No Leave credit data available yat. (Coming Soon)
                </div>
            </div>
        </div>
    </div>
</div>
<script src="<?= url('scripts/profile.js') ?>"></script>