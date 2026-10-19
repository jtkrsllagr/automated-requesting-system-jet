<?php ?>
<div class="form-card">
    <div class="form-section-title">Applicant Details</div>
    <div class="form-grid g-3">
        <div class="form-group"><label>Employee Name</label><input type="text" value="<?= $ro('employee_name') ?>" readonly></div>
        <div class="form-group"><label>Department</label><input type="text" value="<?= $ro('department') ?>" readonly></div>
        <div class="form-group"><label>Date</label><input type="text" value="<?= $ro('request_date') ?>" readonly></div>
    </div>
</div>

<div class="form-card">
    <div class="form-section-title">OT Request Details</div>
    <div class="table-scroll">
        <table class="form-table">
            <thead><tr><th>Date</th><th>Reason/s</th><th>Start</th><th>To</th><th>Total Hours</th></tr></thead>
            <tbody>
            <?php
                $otDates = $data['ot_date'] ?? [];
                $reasons = $data['reason'] ?? [];
                $covered = $data['hours_covered'] ?? [];
                $totals = $data['hours_total'] ?? [];
                // hours_covered is stored as a flat array: [start1, end1, start2, end2, ...]
                $count = max(count($otDates), 1);
                for ($i = 0; $i < $count; $i++):
                    $start = $covered[$i * 2] ?? '';
                    $end = $covered[$i * 2 + 1] ?? '';
            ?>
                <tr>
                    <td><input type="text" value="<?= htmlspecialchars($otDates[$i] ?? '') ?>" readonly></td>
                    <td><input type="text" value="<?= htmlspecialchars($reasons[$i] ?? '') ?>" readonly></td>
                    <td><input type="text" value="<?= htmlspecialchars($start) ?>" readonly></td>
                    <td><input type="text" value="<?= htmlspecialchars($end) ?>" readonly></td>
                    <td><input type="text" value="<?= htmlspecialchars($totals[$i] ?? '') ?>" readonly></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <div class="form-grid g-4 mt-1">
        <div class="form-group"><label>Total Hours Rendered</label><input type="number" value="<?= $ro('total_hours') ?>" readonly></div>
    </div>
</div>

<div class="form-card">
    <div class="form-section-title">Submitted</div>
    <div class="form-group"><input type="text" value="<?= htmlspecialchars(date('M d, Y · g:i A', strtotime($form['created_at']))) ?>" readonly></div>
</div>
