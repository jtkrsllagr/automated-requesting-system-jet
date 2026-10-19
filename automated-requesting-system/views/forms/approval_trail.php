<?php
    /**
     * Approval Trail partial
     * Expects: 
     *  $approvalSteps — rows from approvals JOIN employees (full_name, sequence, status, remarks, approved_at)
     *  $form['status'] — current form status
     */

    $icons = [
        1 => 'ti-send',
        2 => 'ti-user-check',
        3 => 'ti-building-bank',
        4 => 'ti-shield-check',
        5 => 'ti-circle-check',
        6 => 'ti-circle-check',
    ];

    $formType = $form['form_type'] ?? '';
    $isAdmin = \App\Helpers\FormLabels::isAdminForm($formType);
    if ($isAdmin) {
        $vehicleRequest = $formType === 'vehicle_request';
        $labels = [
            1 => ['name' => 'Requestor', 'role' => \App\Helpers\FormLabels::stepLabel(1, $formType)],
            2 => ['name' => 'Immediate Supervisor', 'role' => \App\Helpers\FormLabels::stepLabel(2, $formType)],
            3 => ['name' => $vehicleRequest ? 'Admin Department Immediate Head' : 'Department Head', 'role' => \App\Helpers\FormLabels::stepLabel(3, $formType)],
            4 => ['name' => $vehicleRequest ? 'AdminApprover' : 'Final Approver', 'role' => \App\Helpers\FormLabels::stepLabel(4, $formType)],
        ];
    } else {
        $labels = [
            1 => ['name' => 'Requestor', 'role' => \App\Helpers\FormLabels::stepLabel(1, $formType)],
            2 => ['name' => 'Immediate Supervisor', 'role' => \App\Helpers\FormLabels::stepLabel(2, $formType)],
            3 => ['name' => 'Acquisition Checker', 'role' => \App\Helpers\FormLabels::stepLabel(3, $formType)],
            4 => ['name' => 'Finance Head', 'role' => \App\Helpers\FormLabels::stepLabel(4, $formType)],
            5 => ['name' => 'Final Approver', 'role' => \App\Helpers\FormLabels::stepLabel(5, $formType)],
        ];
    }
    $maxSeq = max(array_keys($labels));

    // Index by sequence (DB column), not level
    $stepsBySeq = [];
    foreach ($approvalSteps as $s) {
        $stepsBySeq[(int)$s['sequence']] = $s;
    }

    // Find the lowest pending sequence — that's the active step
    $activeSec = null;
    foreach ($stepsBySeq as $seq => $s) {
        if ($s['status'] === 'pending') {
            $activeSec = $seq;
            break;
        }
    }
?>

<div class="form-section-title">Approval Trail</div>
<div class="approval-trail">
<?php 
    $isSubmitted = !in_array($form['status'], ['draft'], true);
    for ($seq = 1; $seq <= $maxSeq; $seq++):
        
    $step = $stepsBySeq[$seq] ?? null;
    $status = $step['status'] ?? null;
    if ($formType === 'vehicle_request' && $seq === 2 && $step === null) {
        continue;
    }

    if ($seq === 1) {
        $dotClass = $isSubmitted ? 'done' : 'current';
        $timeText = $isSubmitted
            ? date('M d, Y h:i A', strtotime($form['created_at']))
            : 'Not yet submitted';

        if ($dotClass === 'current') {
            $elapsed = $timeText;
            $diff = (object)['days' => 0];
        }
    } elseif ($status === 'approved') {
        $dotClass = 'done';
        $timeText = $step['approved_at']
            ? date('M d, Y h:i A', strtotime($step['approved_at']))
            : 'Approved';
    } elseif ($status === 'rejected') {
        $dotClass = 'rejected';
        $timeText = $step['approved_at']
            ? date('M d, Y h:i A', strtotime($step['approved_at']))
            : 'Rejected';
    } elseif ($status === 'pending') {
        $dotClass = ($seq === $activeSec) ? 'current' : 'pending';
        if ($seq === $activeSec) {
            // Show how long this step has been waiting
            $since = $step['updated_at'] ?? $step['created_at'] ?? null;
            if ($since) {
                $diff = (new DateTime())->diff(new DateTime($since));
                $elapsed = $diff->days > 0
                    ? 'Waiting ' . $diff->days . ' day' . ($diff->days > 1 ? 's' : '')
                    : ($diff->h > 0 ? 'Waiting ' . $diff->h . 'h' : 'Just assigned');
            } else {
                $elapsed = 'Awaiting approval';
            }
            $timeText = $elapsed;
        } else {
            $timeText = 'Pending';
        }
    } else {
        // No DB row yet for this sequence
        $dotClass = 'pending';
        $timeText = 'Pending';
    }

    // Use the actual approver name from the JOIN, fall back to generic label
    $name = $step['full_name'] ?? ($labels[$seq]['name'] ?? "Step $seq");
    $role = $labels[$seq]['role'] ?? '';
    $icon = $icons[$seq] ?? 'ti-circle';
    $remarks = trim((string) ($step['remarks'] ?? ''));
?>
    <div class="approval-step <?= $dotClass === 'done' ? 'is-done' : ($dotClass === 'rejected' ? 'is-rejected' : '') ?>">
        <div class="step-dot <?= $dotClass ?>"><i class="ti <?= $icon ?>"></i></div>
        <div class="step-info">
            <div class="step-name"><?= htmlspecialchars($name) ?></div>
            <div class="step-role"><?= htmlspecialchars($role) ?></div>
            <?php if ($dotClass === 'current'): ?>
                <div class="step-time">
                    <span class="step-elapsed <?= $diff->days >= 3 ? 'step-elapsed--overdue' : '' ?>">
                        <i class="ti ti-clock ti-xs"></i>
                        <?= htmlspecialchars($elapsed) ?>
                    </span>
                </div>
            <?php else: ?>
                <div class="step-time"><?= htmlspecialchars($timeText) ?></div>
            <?php endif; ?>
            <?php if ($remarks !== ''): ?>
                <div class="step-remark">
                    <i class="ti ti-quote"></i><?= htmlspecialchars($remarks) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endfor; ?>
</div>