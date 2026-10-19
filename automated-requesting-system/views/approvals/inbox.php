<?php $viewMode = $viewMode ?? 'pending'; ?>

<div class="page-heading"><?= $viewMode === 'history' ? 'Approval History' : 'Approval Inbox' ?></div>
<div class="page-subheading">
    <?= $viewMode === 'history'
        ? 'Requests you (or a peer sharing your approval stage) already approved or rejected.'
        : 'Review and act on requests routed to you for approval.' ?>
</div>

<div class="sort-toggle" style="margin-bottom: 1rem;">
    <a href="<?= url('approvals') ?>" class="sort-btn <?= $viewMode === 'pending' ? 'active' : '' ?>">
        <i class="ti ti-inbox"></i> Pending
    </a>
    <a href="<?= url('approvals?view=history') ?>" class="sort-btn <?= $viewMode === 'history' ? 'active' : '' ?>">
        <i class="ti ti-history"></i> History
    </a>
</div>

<?php
    $formLabel = $formLabel ?? [];
    $iconMap = [
        'advance_payment' => ['bg' => '#d1fae5', 'color' => '#10b981', 'icon' => 'ti-cash'],
        'overtime_authorization' => ['bg' => '#ede9fe', 'color' => '#8b5cf6', 'icon' => 'ti-clock-hour-4'],
        'request_for_payment' => ['bg' => '#fce7f3', 'color' => '#ec4899', 'icon' => 'ti-receipt'],
        'leave_application' => ['bg' => '#dbeafe', 'color' => '#0ea5e9', 'icon' => 'ti-beach'],
        'reimbursement' => ['bg' => '#ffedd5', 'color' => '#f97316', 'icon' => 'ti-credit-card-refund'],
        'liquidation' => ['bg' => '#e0f2fe', 'color' => '#0284c7', 'icon' => 'ti-calculator'],
        'vehicle_request' => ['bg' => '#fef9c3', 'color' => '#ca8a04', 'icon' => 'ti-car'],
    ];
    $uniqueTypes = array_unique(array_column($approvals ?? [], 'form_type'));
    sort($uniqueTypes);
?>

<?php if (empty($approvals)): ?>
    <div class="empty-state">
        <i class="ti <?= $viewMode === 'history' ? 'ti-history' : 'ti-inbox-off' ?> empty-state-icon"></i>
        <div class="inbox-empty-title"><?= $viewMode === 'history' ? 'Nothing here yet' : "You're all caught up!" ?></div>
        <div class="inbox-empty0sub">
            <?= $viewMode === 'history'
                ? 'No approved or rejected requests to show yet.'
                : 'No pending approvals at this time.' ?>
        </div>
        <a href="<?= url('my-submissions') ?>" class="btn btn-ghost btn-sm">
            <i class="ti ti-send"></i> View My Submissions
        </a>
    </div>
<?php elseif ($viewMode === 'history'): ?>

<?php
    $totalActioned = count($approvals);
    $approvedCount = count(array_filter($approvals, fn($r) => $r['step_status'] === 'approved'));
    $rejectedCount = count(array_filter($approvals, fn($r) => $r['step_status'] === 'rejected'));
?>

<div class="kpi-grid">
    <a href="?view=history" class="kpi-card blue kpi-card--link">
        <div class="kpi-icon blue"><i class="ti ti-history"></i></div>
        <div class="kpi-label">Total Actioned</div>
        <div class="kpi-value"><?= $totalActioned ?></div>
        <div class="kpi-delta">Most recent 100 decisions</div>
    </a>
    <a href="?view=history" class="kpi-card green kpi-card--link">
        <div class="kpi-icon green"><i class="ti ti-circle-check"></i></div>
        <div class="kpi-label">Approved</div>
        <div class="kpi-value"><?= $approvedCount ?></div>
        <div class="kpi-delta">Steps you or a peer approved</div>
    </a>
    <a href="?view=history" class="kpi-card purple kpi-card--link">
        <div class="kpi-icon purple"><i class="ti ti-circle-x"></i></div>
        <div class="kpi-label">Rejected</div>
        <div class="kpi-value"><?= $rejectedCount ?></div>
        <div class="kpi-delta">Steps you or a peer rejected</div>
    </a>
</div>

<div class="table-wrap">
    <div class="filter-bar" data-filter-bar>
        <input type="search" placeholder="Search by name, department, form…" data-search-input aria-label="Search history">
        <select data-filter-select aria-label="Filter by form type">
            <option value="">All form types</option>
            <?php foreach ($uniqueTypes as $ft): ?>
                <option value="<?= htmlspecialchars($formLabel[$ft] ?? $ft) ?>"><?= htmlspecialchars($formLabel[$ft] ?? $ft) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="badge badge-warning"><?= count($approvals) ?> record<?= count($approvals) === 1 ? '' : 's' ?></span>
        <span class="filter-count" data-filter-count></span>
    </div>
    <table data-filterable data-search-col="0,1,2,3,5" data-filter-col="0">
        <thead>
            <tr>
                <th class="th-first">Form</th>
                <th>Submitted By</th>
                <th>Department</th>
                <th>Stage</th>
                <th>Result</th>
                <th>Acted By</th>
                <th>Date Actioned</th>
                <th class="td-last"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($approvals as $row):
            $ic = $iconMap[$row['form_type']] ?? ['bg' => '#e2e8f0', 'color' => '#64748b', 'icon' => 'ti-file'];
            $typeLabel = $formLabel[$row['form_type']] ?? $row['form_type'];
            $stepLabel = \App\Helpers\FormLabels::stepLabel((int)$row['sequence'], $row['form_type']);
            if (
                (int) $row['sequence'] === 2
                && in_array($row['form_type'], ['leave_application', 'overtime_authorization'], true)
                && preg_match('/\b(management|human\s+resources|hr|admin(?:istration)?)\b/i', (string) ($row['department'] ?? '')) === 1
            ) {
                $stepLabel = 'Final Authorization';
            }
            $isApproved = $row['step_status'] === 'approved';
        ?>
            <tr>
                <td class="td-first">
                    <div class="inbox-form-cell">
                        <div class="activity-icon activity-icon-dynamic"
                            data-bg="<?= htmlspecialchars($ic['bg']) ?>"
                            data-color="<?= htmlspecialchars($ic['color']) ?>"
                        >
                        </div>
                        <div>
                            <div class="inbox-form-name"><?= htmlspecialchars($typeLabel) ?></div>
                            <div class="inbox-form-id">#<?= $row['id'] ?></div>
                        </div>
                    </div>
                </td>
                <td><?= htmlspecialchars($row['owner_name']) ?></td>
                <td class="muted"><?= htmlspecialchars($row['department'] ?? '—') ?></td>
                <td><span class="badge badge-primary"><?= htmlspecialchars($stepLabel) ?></span></td>
                <td>
                    <span class="badge <?= $isApproved ? 'badge-success' : 'badge-danger' ?>">
                        <i class="ti <?= $isApproved ? 'ti-circle-check' : 'ti-circle-x' ?>"></i>
                        <?= $isApproved ? 'Approved' : 'Rejected' ?>
                    </span>
                </td>
                <td class="muted"><?= htmlspecialchars($row['acted_by_name']) ?></td>
                <td class="muted"><?= $row['approved_at'] ? date('M d, Y', strtotime($row['approved_at'])) : '—' ?></td>
                <td class="td-last text-end">
                    <a href="<?= url('forms/view/' . $row['id']) ?>" class="btn btn-ghost btn-sm">
                        View <i class="ti ti-arrow-right ti-xs"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php else: ?>

<?php
    $totalPending = count($approvals);
    $overdue = count(array_filter($approvals, fn($r) => $r['days_pending'] >= 3));
    $byType = array_count_values(array_column($approvals, 'form_type'));
    arsort($byType);
    $topType = key($byType) ?? null;
?>

<div class="kpi-grid">
    <a href="?" class="kpi-card blue kpi-card--link">
        <div class="kpi-icon blue"><i class="ti ti-inbox"></i></div>
        <div class="kpi-label">Pending</div>
        <div class="kpi-value"><?= $totalPending ?></div>
        <div class="kpi-delta">Forms waiting for your action</div>
    </a>

    <a href="?overdue=1" class="kpi-card amber kpi-card--link">
        <div class="kpi-icon amber"><i class="ti ti-alert-triangle"></i></div>
        <div class="kpi-label">Overdue (3+ days)</div>
        <div class="kpi-value"><?= $overdue ?></div>
        <div class="kpi-delta">Immediate decision required</div>
    </a>

    <?php if ($topType): 
        $topIc = $iconMap[$topType] ?? ['bg' => '#ede9fe', 'color' => '#8b5cf6', 'icon' => 'ti-file'];
    ?>
    <a href="?type=<?= urlencode($topType) ?>" class="kpi-card purple kpi-card--link">
        <div class="kpi-icon purple"><i class="ti <?= $topIc['icon'] ?>"></i></div>
        <div class="kpi-label">Most Frequent</div>
        <div class="kpi-value"><?= $byType[$topType] ?></div>
        <div class="kpi-delta"><?= htmlspecialchars($formLabel[$topType] ?? $topType) ?></div>
    </a>
    <?php endif; ?>
</div>

<div class="table-wrap">
    <div class="filter-bar" data-filter-bar>
        <input type="search" placeholder="Search by name, department, form…" data-search-input aria-label="Search approvals">
        <select data-filter-select aria-label="Filter by form type">
            <option value="">All form types</option>
            <?php foreach ($uniqueTypes as $ft): ?>
                <option value="<?= htmlspecialchars($formLabel[$ft] ?? $ft) ?>"><?= htmlspecialchars($formLabel[$ft] ?? $ft) ?></option>
            <?php endforeach; ?>
        </select>
        
        <span class="badge badge-warning"><?= count($approvals) ?> pending</span>
        <span class="filter-count" data-filter-count></span>

        <div class="sort-toggle">
            <a href="?sort=priority"
               class="sort-btn <?= ($currentSort === 'priority') ? 'active' : '' ?>">
                <i class="ti ti-alert-triangle"></i> Priority
            </a>
            <a href="?sort=date"
               class="sort-btn <?= ($currentSort === 'date') ? 'active' : '' ?>">
                <i class="ti ti-calendar"></i> Date
            </a>
        </div>
    </div>
    <table data-filterable data-search-col="0,1,2,3" data-filter-col="0">
        <thead>
            <tr>
                <th class="th-first">Form</th>
                <th>Submitted By</th>
                <th>Department</th>
                <th>Stage</th>
                <th>Date Filed</th>
                <th>Waiting</th>
                <th class="td-last"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($approvals as $row):
            $ic = $iconMap[$row['form_type']] ?? ['bg' => '#e2e8f0', 'color' => '#64748b', 'icon' => 'ti-file'];
            $ago = (new DateTime())->diff(new DateTime($row['created_at']));
            $waitStr = $ago->days > 0 ? $ago->days . 'd ago' : ($ago->h > 0 ? $ago->h . 'h ago' : 'Just now');
            $isOverdue = $ago->days >= 3;
            $typeLabel = $formLabel[$row['form_type']] ?? $row['form_type'];
        ?>
            <tr class="<?= $isOverdue ? 'row-overdue' : '' ?>"
                data-days="<?= (int)$row['days_pending'] ?>">
                <td class="td-first">
                    <div class="inbox-form-cell">
                        <div class="activity-icon activity-icon-dynamic"
                            data-bg="<?= htmlspecialchars($ic['bg']) ?>"
                            data-color="<?= htmlspecialchars($ic['color']) ?>"
                        >
                        </div>
                        <div>
                            <div class="inbox-form-name"><?= htmlspecialchars($typeLabel) ?></div>
                            <div class="inbox-form-id">#<?= $row['id'] ?></div>
                        </div>
                    </div>
                </td>
                <td><?= htmlspecialchars($row['owner_name']) ?></td>
                <td class="muted"><?= htmlspecialchars($row['department'] ?? '—') ?></td>
                <td>
                    <?php
                        $stepLabel = \App\Helpers\FormLabels::stepLabel((int)$row['sequence'], $row['form_type']);
                        $isDirectFinalApproval = (int) $row['sequence'] === 2
                            && in_array($row['form_type'], ['leave_application', 'overtime_authorization'], true)
                            && preg_match('/\b(management|human\s+resources|hr|admin(?:istration)?)\b/i', (string) ($row['department'] ?? '')) === 1;
                        if ($isDirectFinalApproval) {
                            $stepLabel = 'Final Authorization';
                        }
                        $isSupervisorFlowFinalApproval = (int) $row['sequence'] === 3
                            && in_array($row['form_type'], ['leave_application', 'overtime_authorization'], true)
                            && preg_match('/\b(accounting|information\s+technology|it|finance|logistics)\b|(?<![a-z-])sales\b|\bpre-sales\s*\(engineer\)|\bpost-sales\s*\(engineer\)/i', (string) ($row['department'] ?? '')) === 1;
                        if ($isSupervisorFlowFinalApproval) {
                            $stepLabel = 'Final Authorization';
                        }
                        // HR Verifier co-signs Process Approval alongside the
                        // Finance/Accounting Checker on the same sequence for
                        // Reimbursement/Liquidation — label it distinctly so
                        // HR knows what they're confirming.
                        if ((int)($_SESSION['role_id'] ?? 0) === 9 && in_array($row['form_type'], ['reimbursement', 'liquidation'], true)) {
                            $stepLabel .= ' — HR Attendance Verification';
                        }
                    ?>
                    <span class="badge badge-primary">
                        <?= htmlspecialchars($stepLabel) ?>
                    </span>
                </td>
                <td class="muted"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                <td>
                    <span class="wait-pill <?= $isOverdue ? 'wait-pill--overdue' : '' ?>">
                        <?php if ($isOverdue): ?>
                            <i class="ti ti-alert-triangle"></i>
                        <?php endif; ?>
                        <?= $waitStr ?>
                    </span>
                </td>
                <td class="td-last text-end">
                    <a href="<?= url('forms/view/' . $row['id']) ?>" class="btn btn-primary btn-sm">
                        <?= $isDirectFinalApproval
                            ? 'Authorize'
                            : \App\Helpers\FormLabels::verb($isSupervisorFlowFinalApproval ? 4 : (int)$row['sequence'], $row['form_type']) ?> <i class="ti ti-arrow-right ti-xs"></i>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>