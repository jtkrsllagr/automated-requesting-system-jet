<div class="page-heading"><?= htmlspecialchars($statusLabel) ?></div>
<div class="page-subheading">All records in this category.</div>

<?php if (empty($forms)): ?>
    <div class="empty-state">
        <i class="ti ti-inbox empty-state-icon"></i>
        No records found.
    </div>
<?php else: ?>
    <?php $badgeMap = \App\Helpers\FormLabels::allBadges(); ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th class="th-first">#</th>
                    <th>Form Type</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th class="td-last"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($forms as $form): ?>
                <tr>
                    <td class="muted td-first"><?= (int) $form['id'] ?></td>
                    <td><?= htmlspecialchars($formLabel[$form['form_type']] ?? $form['form_type']) ?></td>
                    <td>
                        <span class="badge badge-<?= $badgeMap[$form['status']] ?? 'secondary' ?>">
                            <?= htmlspecialchars(\App\Helpers\FormLabels::statusLabel($form['status'])) ?>
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
<?php endif; ?>