<link rel="stylesheet" href="<?= url('stylesheets/department.css') ?>">

<div class="dept-shell">

    <!-- Left panel -->
    <div class="dept-panel">
        <div class="dept-panel-head">
            <div>
                <div class="dept-panel-title">Departments</div>
                <div class="dept-panel-sub"><?= count($departments) ?> total</div>
            </div>
            <button class="btn btn-primary btn-sm" id="openAddModalBtn">
                <i class="ti ti-plus"></i> New
            </button>
        </div>

        <div class="dept-search-wrap">
            <i class="ti ti-search"></i>
            <input type="search" id="deptSearch" placeholder="Search departments…">
        </div>

        <?php if (!empty($_SESSION['success'])): ?>
            <div class="dept-flash dept-flash--success">
                <i class="ti ti-circle-check"></i> <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="dept-flash dept-flash--danger">
                <i class="ti ti-circle-x"></i> <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <ul class="dept-list"
            id="deptList"
            role="listbox"
            aria-label="Departments"
            <?php if (!empty($_SESSION['last_dept_id'])): ?>
                data-auto-select="<?= (int)$_SESSION['last_dept_id'] ?>"
            <?php unset($_SESSION['last_dept_id']); endif; ?>
        >
            <?php if (empty($departments)): ?>
                <li class="dept-empty">
                    <i class="ti ti-building empty-state-icon"></i>
                    <span>No departments yet.</span>
                </li>
            <?php else: ?>
                <?php foreach ($departments as $dept): ?>
                <li class="dept-item"
                    tabindex="0"
                    role="option"
                    data-name="<?= strtolower(htmlspecialchars($dept['name'])) ?>"
                    data-id="<?= $dept['id'] ?>"
                    data-label="<?= htmlspecialchars($dept['name'], ENT_QUOTES) ?>"
                    data-count="<?= $dept['member_count'] ?? 0 ?>"
                    data-forms="<?= $dept['form_count'] ?? 0 ?>"
                    data-initial="<?= htmlspecialchars(strtoupper(substr($dept['name'], 0, 1))) ?>"
                    data-created="<?= date('M Y', strtotime($dept['created_at'])) ?>"
                >
                    <div class="dept-item-icon dept-item-icon--letter"><?= strtoupper(substr($dept['name'], 0, 1)) ?></div>
                    <div class="dept-item-body">
                        <span class="dept-item-name"><?= htmlspecialchars($dept['name']) ?></span>
                        <span class="dept-item-meta">
                            <i class="ti ti-users dept-meta-icon"></i>
                            <?= $dept['member_count'] ?? 0 ?> members
                        </span>
                    </div>
                    <i class="ti ti-chevron-right dept-item-arrow"></i>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Right panel -->
    <div class="dept-detail" id="deptDetail">
        <div class="dept-detail-empty" id="deptDetailEmpty">
            <i class="ti ti-building-skyscraper dept-placeholder-icon"></i>
            <p>Pick a department to view details</p>
            <button class="btn btn-ghost btn-sm" id="emptyCreateBtn">
                <i class="ti ti-plus"></i> Create one
            </button>
        </div>

        <div class="dept-detail-content dept-hidden" id="deptDetailContent">
            <div class="dept-detail-icon-wrap">
                <div class="dept-detail-icon"></div>
            </div>

            <!-- Inline rename bar -->
            <form method="POST" id="editForm" class="dept-rename-bar dept-hidden">
                <?= \App\Helpers\Csrf::field() ?>
                <button type="button" class="dept-rename-cancel" id="renameCancelBtn" title="Cancel (Esc)">
                    <i class="ti ti-arrow-left"></i>
                </button>
                <div class="dept-field-wrap dept-rename-field">
                    <i class="ti ti-building"></i>
                    <input type="text" name="name" id="editName"
                           class="dept-field-input"
                           placeholder="Department name…"
                           maxlength="100" required>
                </div>
                <button type="submit" class="dept-rename-confirm" title="Rename (Enter)">
                    <i class="ti ti-check"></i>
                </button>
            </form>

            <!-- Normal view -->
            <div class="dept-detail-name" id="detailName"></div>
            <div class="dept-detail-id" id="detailId"></div>

            <div class="dept-detail-stats">
                <div class="dept-stat">
                    <span class="dept-stat-val" id="detailCount">0</span>
                    <span class="dept-stat-label">Members</span>
                </div>
                <div class="dept-stat-divider"></div>
                <div class="dept-stat">
                    <span class="dept-stat-val" id="detailForms">0</span>
                    <span class="dept-stat-label">Active Forms</span>
                </div>
            </div>

            <div class="dept-detail-actions">
                <button class="btn btn-ghost btn-sm" id="detailEditBtn" disabled>
                    <i class="ti ti-pencil"></i> Edit
                </button>
                <div class="dept-overflow-wrap">
                    <button class="dept-overflow-btn" id="detailMoreBtn" title="More actions" aria-haspopup="true" aria-expanded="false">
                        <i class="ti ti-dots"></i>
                    </button>
                    <div class="dept-overflow-menu dept-hidden" id="deptOverflowMenu">
                        <button type="button" class="dept-overflow-item dept-overflow-item--danger" id="detailDeleteBtn" disabled>
                            <i class="ti ti-trash"></i> Delete department
                        </button>
                    </div>
                </div>
            </div>

            <div class="dept-members-wrap" id="deptMembersWrap">
                <div class="dept-members-head">
                    <span>Members</span>
                    <span class="dept-members-count" id="deptMembersCount"></span>
                </div>
                <ul class="dept-members-list" id="deptMembersList">
                    <li class="dept-members-loading"><i class="ti ti-loader-2 spin"></i> Loading…</li>
                </ul>
            </div>
        </div>
    </div>

</div>

<!-- Add dialog -->
<dialog id="addDialog" class="dept-dialog">
    <form method="POST" action="<?= url('departments/create') ?>" id="addForm">
        <?= \App\Helpers\Csrf::field() ?>
        <div class="dept-dialog-header">
            <div class="dept-dialog-icon dept-dialog-icon--accent">
                <i class="ti ti-building-plus"></i>
            </div>
            <div>
                <div class="dept-dialog-title">New Department</div>
                <div class="dept-dialog-sub">Add a department to organize employees.</div>
            </div>
        </div>
        <div class="dept-dialog-body">
            <div class="dept-field-wrap">
                <i class="ti ti-building"></i>
                <input type="text" name="name" id="addName"
                       class="dept-field-input"
                       placeholder="e.g. Finance"
                       maxlength="100" required autofocus>
            </div>
        </div>
        <div class="dept-dialog-footer">
            <button type="button" class="btn btn-ghost" id="addCancelBtn">Cancel</button>
            <button type="submit" class="btn btn-primary">Create</button>
        </div>
    </form>
</dialog>

<!-- Hidden delete form -->
<form method="POST" id="detailDeleteForm" class="dept-hidden">
    <?= \App\Helpers\Csrf::field() ?>
</form>

<!-- Confirm delete dialog -->
<dialog id="confirmDeleteDialog" class="dept-dialog">
    <div class="dept-dialog-header">
        <div class="dept-dialog-icon dept-dialog-icon--danger">
            <i class="ti ti-trash"></i>
        </div>
        <div>
            <div class="dept-dialog-title">Delete Department?</div>
            <div class="dept-dialog-sub">This action cannot be undone.</div>
        </div>
    </div>
    <p class="dept-dialog-body">
        <strong id="confirmDeptName"></strong> and all its data will be permanent removed.
    </p>
    <div class="dept-dialog-footer">
        <button type="button" class="btn btn-ghost" id="confirmCancelBtn">Cancel</button>
        <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">Delete</button>
    </div>
</dialog>

<script src="<?= url('scripts/department.js') ?>"></script>