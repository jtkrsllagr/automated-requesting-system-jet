<div class="page-heading-row" style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
    <div>
        <div class="page-heading">All Requests</div>
        <div class="page-subheading">Complete record of all submitted forms across all departments.</div>
    </div>
    <?php if (in_array((int) ($_SESSION['role_id'] ?? 0), [1, 6, 7], true)): ?>
    <div style="display:flex; align-items:center; gap:.5rem;">
        <span class="muted" style="font-size:13px;">Export completed:</span>
        <a href="<?= url('requests/export?format=csv') ?>" class="btn btn-ghost btn-sm">
            <i class="ti ti-file-type-csv"></i> CSV
        </a>
        <a href="<?= url('requests/export?format=xlsx') ?>" class="btn btn-ghost btn-sm">
            <i class="ti ti-file-spreadsheet"></i> Excel
        </a>
        <a href="<?= url('requests/export?format=docx') ?>" class="btn btn-ghost btn-sm">
            <i class="ti ti-file-type-doc"></i> Word
        </a>
        <a href="<?= url('requests/export?format=pdf') ?>" class="btn btn-ghost btn-sm" target="_blank" rel="noopener"
           title="Opens a print-ready page — choose &quot;Save as PDF&quot; in the print dialog">
            <i class="ti ti-file-type-pdf"></i> PDF
        </a>
    </div>
    <?php endif; ?>
</div>

<?php
    $badgeMap = \App\Helpers\FormLabels::allBadges();
    $statusLabels = \App\Helpers\FormLabels::allStatusLabels();

    $uniqueTypes = array_unique(array_column($forms ?? [], 'form_type'));
    sort($uniqueTypes);
    $uniqueDepts = array_unique(array_filter(array_column($forms ?? [], 'department')));
    sort($uniqueDepts);
?>

<?php if (empty($forms)): ?>
    <div class="empty-state">
        <i class="ti ti-file-description empty-state-icon"></i>
        No requests found.
    </div>
<?php else: ?>

<div class="table-wrap">
    <div class="filter-bar" data-filter-bar>
        <input type="search" placeholder="Search by name, department, form…" data-search-input aria-label="Search requests">
        <select data-filter-select aria-label="Filter by form type">
            <option value="">All form types</option>
            <?php foreach ($uniqueTypes as $ft): ?>
                <option value="<?= htmlspecialchars($formLabel[$ft] ?? $ft) ?>"><?= htmlspecialchars($formLabel[$ft] ?? $ft) ?></option>
            <?php endforeach; ?>
        </select>
        <select aria-label="Filter by department" id="deptFilter">
            <option value="">All departments</option>
            <?php foreach ($uniqueDepts as $dept): ?>
                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
            <?php endforeach; ?>
        </select>
        <select aria-label="Filter by status" id="statusFilterAll">
            <option value="">All statuses</option>
            <option value="submitted">Submitted</option>
            <option value="in_approval">In Approval</option>
            <option value="completed">Completed</option>
            <option value="rejected">Rejected</option>
        </select>
        <span class="filter-count" data-filter-count></span>
    </div>
    <table data-filterable data-search-col="1,2,3" data-filter-col="1">
        <thead>
            <tr>
                <th class="th-first">#</th>
                <th>Form Type</th>
                <th>Submitted By</th>
                <th>Department</th>
                <th>Status</th>
                <th>Date</th>
                <th class="td-last"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($forms as $form):
            $humanStatus = $statusLabels[$form['status']] ?? ucwords(str_replace('_', ' ', $form['status']));
        ?>
            <tr data-status="<?= $form['status'] ?>" data-dept="<?= htmlspecialchars($form['department'] ?? '') ?>">
                <td class="muted td-first"><?= $form['id'] ?></td>
                <td><?= htmlspecialchars($formLabel[$form['form_type']] ?? $form['form_type']) ?></td>
                <td><?= htmlspecialchars($form['full_name']) ?></td>
                <td class="muted"><?= htmlspecialchars($form['department'] ?? '—') ?></td>
                <td>
                    <span class="badge badge-<?= $badgeMap[$form['status']] ?? 'secondary' ?>">
                        <?= htmlspecialchars($humanStatus) ?>
                    </span>
                </td>
                <td class="muted"><?= date('M d, Y', strtotime($form['created_at'])) ?></td>
                <td class="td-last text-end">
                    <a href="<?= url('forms/view/' . $form['id']) ?>" class="btn btn-ghost btn-sm">View</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="<?= url('scripts/all_requests.js') ?>"></script>
<?php endif; ?>