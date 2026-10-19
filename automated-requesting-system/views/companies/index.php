<link rel="stylesheet" href="<?= url('stylesheets/company.css') ?>">

<?php
    function companyLogoUrl(?string $logo): ?string {
        if (empty($logo)) return null;
        $logo = ltrim($logo, '/');
        if (str_starts_with($logo, 'http')) return $logo;
        if (!str_starts_with($logo, 'uploads/companies/')) $logo = 'uploads/companies/' . $logo;
        return url($logo);
    }
?>

<div class="comp-shell">

    <!-- Left panel -->
    <div class="comp-panel">
        <div class="comp-panel-head">
            <div>
                <div class="comp-panel-title">Companies</div>
                <div class="comp-panel-sub"><?= count($companies) ?> total</div>
            </div>
            <button class="btn btn-primary btn-sm" id="openAddModalBtn">
                <i class="ti ti-plus"></i> New
            </button>
        </div>

        <div class="comp-search-wrap">
            <i class="ti ti-search"></i>
            <input type="search" id="compSearch" placeholder="Search companies…">
        </div>

        <?php if (!empty($_SESSION['success'])): ?>
            <div class="comp-flash comp-flash--success">
                <i class="ti ti-circle-check"></i> <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="comp-flash comp-flash--danger">
                <i class="ti ti-circle-x"></i> <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <ul class="comp-list"
            id="compList"
            role="listbox"
            aria-label="Companies"
            <?php if (!empty($_SESSION['last_company_id'])): ?>
                data-auto-select="<?= (int)$_SESSION['last_company_id'] ?>"
            <?php unset($_SESSION['last_company_id']); endif; ?>
        >
            <?php if (empty($companies)): ?>
                <li class="comp-empty">
                    <i class="ti ti-building empty-state-icon"></i>
                    <span>No companies yet.</span>
                </li>
            <?php else: ?>
                <?php foreach ($companies as $company): ?>
                <?php $logoUrl = companyLogoUrl($company['logo'] ?? null); ?>
                <li class="comp-item"
                    tabindex="0"
                    role="option"
                    data-name="<?= strtolower(htmlspecialchars($company['name'])) ?>"
                    data-id="<?= $company['id'] ?>"
                    data-label="<?= htmlspecialchars($company['name'], ENT_QUOTES) ?>"
                    data-count="<?= $company['member_count'] ?? 0 ?>"
                    data-initial="<?= htmlspecialchars(strtoupper(substr($company['name'], 0, 1))) ?>"
                    data-created="<?= date('M Y', strtotime($company['created_at'])) ?>"
                    data-logo="<?= htmlspecialchars($logoUrl ?? '') ?>"
                >
                    <?php if ($logoUrl): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="" class="comp-item-icon comp-item-icon--logo">
                    <?php else: ?>
                        <div class="comp-item-icon comp-item-icon--letter"><?= strtoupper(substr($company['name'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <div class="comp-item-body">
                        <span class="comp-item-name"><?= htmlspecialchars($company['name']) ?></span>
                        <span class="comp-item-meta">
                            <i class="ti ti-users comp-meta-icon"></i>
                            <?= $company['member_count'] ?? 0 ?> members
                        </span>
                    </div>
                    <i class="ti ti-chevron-right comp-item-arrow"></i>
                </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Right panel -->
    <div class="comp-detail" id="compDetail">
        <div class="comp-detail-empty" id="compDetailEmpty">
            <i class="ti ti-building-skyscraper comp-placeholder-icon"></i>
            <p>Pick a company to view details</p>
            <button class="btn btn-ghost btn-sm" id="emptyCreateBtn">
                <i class="ti ti-plus"></i> Create one
            </button>
        </div>

        <div class="comp-detail-content comp-hidden" id="compDetailContent">
            <div class="comp-detail-icon-wrap">
                <div class="comp-detail-icon" id="detailIcon"></div>
                <img class="comp-detail-logo comp-hidden" id="detailLogo" alt="">
                <form method="POST" id="logoForm" enctype="multipart/form-data" class="comp-logo-form">
                    <?= \App\Helpers\Csrf::field() ?>
                    <label for="logoInput" class="comp-logo-edit-btn" title="Change logo">
                        <i class="ti ti-camera"></i>
                    </label>
                    <input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
                </form>
            </div>

            <!-- Inline rename bar -->
            <form method="POST" id="editForm" class="comp-rename-bar comp-hidden">
                <?= \App\Helpers\Csrf::field() ?>
                <button type="button" class="comp-rename-cancel" id="renameCancelBtn" title="Cancel (Esc)">
                    <i class="ti ti-arrow-left"></i>
                </button>
                <div class="comp-field-wrap comp-rename-field">
                    <i class="ti ti-building"></i>
                    <input type="text" name="name" id="editName"
                           class="comp-field-input"
                           placeholder="Company name…"
                           maxlength="150" required>
                </div>
                <button type="submit" class="comp-rename-confirm" title="Rename (Enter)">
                    <i class="ti ti-check"></i>
                </button>
            </form>

            <!-- Normal view -->
            <div class="comp-detail-name" id="detailName"></div>
            <div class="comp-detail-id" id="detailId"></div>

            <div class="comp-detail-stats">
                <div class="comp-stat">
                    <span class="comp-stat-val" id="detailCount">0</span>
                    <span class="comp-stat-label">Members</span>
                </div>
            </div>

            <div class="comp-detail-actions">
                <button class="btn btn-ghost btn-sm" id="detailEditBtn" disabled>
                    <i class="ti ti-pencil"></i> Edit
                </button>
                <div class="comp-overflow-wrap">
                    <button class="comp-overflow-btn" id="detailMoreBtn" title="More actions" aria-haspopup="true" aria-expanded="false">
                        <i class="ti ti-dots"></i>
                    </button>
                    <div class="comp-overflow-menu comp-hidden" id="compOverflowMenu">
                        <button type="button" class="comp-overflow-item comp-overflow-item--danger" id="detailDeleteBtn" disabled>
                            <i class="ti ti-trash"></i> Delete company
                        </button>
                    </div>
                </div>
            </div>

            <div class="comp-members-wrap" id="compMembersWrap">
                <div class="comp-members-head">
                    <span>Members</span>
                    <span class="comp-members-count" id="compMembersCount"></span>
                </div>
                <ul class="comp-members-list" id="compMembersList">
                    <li class="comp-members-loading"><i class="ti ti-loader-2 spin"></i> Loading…</li>
                </ul>
            </div>
        </div>
    </div>

</div>

<!-- Add dialog -->
<dialog id="addDialog" class="comp-dialog">
    <form method="POST" action="<?= url('companies/create') ?>" id="addForm" enctype="multipart/form-data">
        <?= \App\Helpers\Csrf::field() ?>
        <div class="comp-dialog-header">
            <div class="comp-dialog-icon comp-dialog-icon--accent">
                <i class="ti ti-building-plus"></i>
            </div>
            <div>
                <div class="comp-dialog-title">New Company</div>
                <div class="comp-dialog-sub">Add a company employees can be assigned to.</div>
            </div>
        </div>
        <div class="comp-dialog-body">
            <div class="comp-field-wrap">
                <i class="ti ti-building"></i>
                <input type="text" name="name" id="addName"
                       class="comp-field-input"
                       placeholder="e.g. 3E Hitech Solutions"
                       maxlength="150" required autofocus>
            </div>
            <div class="comp-field-wrap comp-field-wrap--file">
                <label for="addLogo" class="comp-field-file-label">
                    <i class="ti ti-photo"></i> <span id="addLogoFileName">Add a logo (optional)</span>
                </label>
                <input type="file" name="logo" id="addLogo" accept="image/png,image/jpeg,image/webp,image/gif">
            </div>
        </div>
        <div class="comp-dialog-footer">
            <button type="button" class="btn btn-ghost" id="addCancelBtn">Cancel</button>
            <button type="submit" class="btn btn-primary">Create</button>
        </div>
    </form>
</dialog>

<!-- Hidden delete form -->
<form method="POST" id="detailDeleteForm" class="comp-hidden">
    <?= \App\Helpers\Csrf::field() ?>
</form>

<!-- Confirm delete dialog -->
<dialog id="confirmDeleteDialog" class="comp-dialog">
    <div class="comp-dialog-header">
        <div class="comp-dialog-icon comp-dialog-icon--danger">
            <i class="ti ti-trash"></i>
        </div>
        <div>
            <div class="comp-dialog-title">Delete Company?</div>
            <div class="comp-dialog-sub">This action cannot be undone.</div>
        </div>
    </div>
    <p class="comp-dialog-body">
        <strong id="confirmCompName"></strong> and all its data will be permanently removed.
    </p>
    <div class="comp-dialog-footer">
        <button type="button" class="btn btn-ghost" id="confirmCancelBtn">Cancel</button>
        <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">Delete</button>
    </div>
</dialog>

<script src="<?= url('scripts/company.js') ?>"></script>
