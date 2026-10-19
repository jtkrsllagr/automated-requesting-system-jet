<div class="page-header">
    <div>
        <div class="page-heading">Employees</div>
        <div class="page-subheading">Manage accounts, roles, and employment status.</div>
    </div>
    <a href="<?= url('employees/create') ?>" class="btn btn-primary btn-sm">
        <i class="ti ti-user-plus"></i> Add Employee
    </a>
</div>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success alert-icon">
        <i class="ti ti-circle-check"></i>
        <?= htmlspecialchars($_SESSION['success']) ?>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-icon">
        <i class="ti ti-alert-circle"></i>
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="table-wrap">
    <div class="filter-bar" data-filter-bar>
        <input type="search" placeholder="Search by name, code, or email…" data-search-input aria-label="Search employees">
        <select data-filter-select aria-label="Filter by role">
            <option value="">All roles</option>
            <?php
                $uniqueRoles = array_unique(array_filter(array_column($employees, 'role_name')));
                sort($uniqueRoles);
                foreach ($uniqueRoles as $r):
            ?>
                <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="filter-count" data-filter-count></span>
    </div>

    <table data-filterable data-search-col="0,1,2" data-filter-col="3">
        <thead>
            <tr>
                <th class="th-first">Employee</th>
                <th>Email</th>
                <th>Department</th>
                <th>Company</th>
                <th>Role</th>
                <th>Supervisor</th>
                <th>Status</th>
                <th>Employment</th>
                <th class="td-last"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($employees as $e):
                $initials = strtoupper(substr($e['full_name'] ?? 'U', 0, 1) . (strstr($e['full_name'] ?? '', ' ') ? substr(strstr($e['full_name'], ' '), 1, 1) : ''));
                $empStatus = $e['employment_status'] ?? 'employed';
                $empStatusClass = match($empStatus) {
                    'resigned' => 'emp-sel--resigned',
                    'floating' => 'emp-sel--floating',
                    default    => 'emp-sel--employed',
                };
            ?>
            <tr>
                <td class="td-first">
                    <div class="emp-cell">
                        <?php $empAvatarUrl = avatar_url($e['avatar'] ?? null); ?>
                        <?php if ($empAvatarUrl): ?>
                            <img src="<?= $empAvatarUrl ?>" class="emp-avatar emp-avatar-img" alt="">
                        <?php else: ?>
                            <div class="emp-avatar"><?= htmlspecialchars($initials) ?></div>
                        <?php endif; ?>
                        <div>
                            <div class="emp-name"><?= htmlspecialchars($e['full_name'] ?? '') ?></div>
                            <div class="emp-code"><?= htmlspecialchars($e['employee_code'] ?? '') ?></div>
                        </div>
                    </div>
                </td>
                <td class="muted"><?= htmlspecialchars($e['email'] ?? '') ?></td>
                <td class="muted"><?= htmlspecialchars($e['department'] ?? '—') ?></td>
                <td class="muted"><?= htmlspecialchars($e['company'] ?? '—') ?></td>
                <td>
                    <?php if (!empty($e['role_name'])): ?>
                        <span class="badge badge-primary"><?= htmlspecialchars($e['role_name']) ?></span>
                    <?php else: ?>
                        <span class="muted">N/A</span>
                    <?php endif; ?>
                </td>
                <td class="muted"><?= htmlspecialchars($e['supervisor_name'] ?? 'None') ?></td>
                <td>
                    <?php if ($e['is_active']): ?>
                        <span class="badge badge-success"><span class="badge-dot badge-dot--success"></span> Active</span>
                    <?php else: ?>
                        <span class="badge badge-secondary"><span class="badge-dot badge-dot--inactive"></span> Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" action="<?= url('employees/' . $e['id'] . '/status') ?>">
                        <?= \App\Helpers\Csrf::field() ?>
                        <select name="employment_status" class="form-select-sm auto-submit <?= $empStatusClass ?>">
                            <?php foreach (['employed', 'resigned', 'floating'] as $s): ?>
                                <option value="<?= $s ?>" <?= $empStatus === $s ? 'selected' : '' ?>>
                                    <?= ucfirst($s) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td class="td-last">
                    <div class="flex-actions">
                        <a href="<?= url('employees/edit/' . $e['id']) ?>" class="btn btn-ghost btn-sm" title="Edit">
                            <i class="ti ti-edit"></i> Edit
                        </a>
                        <?php if ($e['id'] != $_SESSION['user_id']): ?>
                        <form method="POST"
                            action="<?= url('employees/' . $e['id'] . '/delete') ?>"
                            id="deleteEmpForm-<?= $e['id'] ?>"
                            class="emp-delete-form">
                            <?= \App\Helpers\Csrf::field() ?>
                        </form>
                        <button type="button"
                                class="btn btn-outline-danger btn-sm"
                                title="Deactivate"
                                data-emp-delete="<?= $e['id'] ?>"
                                data-emp-name="<?= htmlspecialchars($e['full_name'], ENT_QUOTES) ?>"
                                data-emp-pending="<?= (int)($e['pending_approvals'] ?? 0) ?>">
                            <i class="ti ti-user-off"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Employee deactivate confirmation modal -->
<div class="ars-modal-backdrop" id="deactivateEmpModal" role="dialog" aria-modal="true" aria-labelledby="deactivateEmpTitle" hidden>
    <div class="ars-modal">
        <div class="ars-modal-icon ars-modal-icon--danger">
            <i class="ti ti-user-off"></i>
        </div>
        <h3 class="ars-modal-title" id="deactivateEmpTitle">Deactivate employee?</h3>
        <p class="ars-modal-body">
            <strong id="deactivateEmpName"></strong> will lose access to the system immediately.
        </p>
        <div class="ars-modal-warning" id="deactivateEmpPendingWarn" hidden>
            <i class="ti ti-alert-triangle"></i>
            <span>This employee has <strong id="deactivateEmpPendingCount"></strong> pending approval step(s) that will be reassigned automatically.</span>
        </div>
        <div class="ars-modal-actions">
            <button type="button" class="btn btn-ghost btn-sm" id="deactivateEmpCancelBtn">
                Cancel
            </button>
            <button type="button" class="btn btn-danger btn-sm" id="deactivateEmpConfirmBtn">
                <i class="ti ti-user-off"></i> Yes, deactivate
            </button>
        </div>
    </div>
</div>