<?php
    $formLabel = [
        'advance_payment' => 'Advance Payment',
        'overtime_authorization' => 'Overtime Authorization',
        'request_for_payment' => 'Request for Payment',
        'work_permit' => 'Work Permit',
        'leave_application' => 'Leave Application',
        'reimbursement' => 'Reimbursement',
        'liquidation' => 'Liquidation',
        'vehicle_request' => 'Vehicle Request',
    ];

    $statusBadge = [
        'draft' => 'secondary',
        'submitted' => 'primary',
        'in_approval' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'dark',
    ];

    $roleId = $_SESSION['role_id'];
    $title = $formLabel[$formType] ?? $formType;
    $hasCheckVoucher = in_array($formType, [
        'advance_payment',
        'request_for_payment',
        'reimbursement',
        'liquidation',
    ], true);
?>

<div class="page-header">
    <h5><?= htmlspecialchars($title) ?></h5>
    <?php if ($roleId != 2 && $roleId != 7): ?>
        <a href="<?= url('forms/' . $slug . '/create') ?>" class="btn btn-primary btn-sm">+ New Request</a>
    <?php endif; ?>
</div>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (empty($forms)): ?>
    <div class="empty-state">
        <div class="empty-icon">📭</div>
        No <?= htmlspecialchars($title) ?> forms found.
    </div>
<?php else: ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th class="th-first">#</th>
                <?php if ($hasCheckVoucher): ?><th>Check Voucher</th><?php endif; ?>
                <?php if ($roleId != 3): ?><th>Submitted By</th><?php endif; ?>
                <th>Status</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($forms as $form): ?>
            <tr>
                <td class="muted td-first"><?= $form['id'] ?></td>
                <?php if ($hasCheckVoucher): ?>
                    <td><?= htmlspecialchars($form['check_voucher'] ?? '') ?></td>
                <?php endif; ?>
                <?php if ($roleId != 3): ?>
                    <td><?= htmlspecialchars($form['full_name']) ?></td>
                <?php endif; ?>
                <td>
                    <span class="badge badge-<?= $statusBadge[$form['status']] ?? 'secondary' ?>">
                        <?= ucfirst(str_replace('_', ' ', $form['status'])) ?>
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