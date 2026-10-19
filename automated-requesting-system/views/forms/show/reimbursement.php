<?php // $data and $ro() are set by show.php ?>
<div class="form-card">
    <div class="form-section-title">Applicant Details</div>
    <div class="form-grid g-4">
        <div class="form-group"><label>Transaction Code</label><input type="text" value="<?= $ro('transaction_code') ?>" readonly></div>
        <div class="form-group"><label>Name</label><input type="text" value="<?= $ro('employee_name') ?>" readonly></div>
        <div class="form-group"><label>Department</label><input type="text" value="<?= $ro('department') ?>" readonly></div>
        <div class="form-group"><label>Pages</label><input type="text" value="<?= $ro('page_no') ?>" readonly></div>
        <div class="form-group"><label>Date</label><input type="text" value="<?= $ro('request_date') ?>" readonly></div>
    </div>
</div>

<div class="form-card">
    <div class="form-section-title">Reimbursement Details</div>
    <div class="table-scroll">
        <table class="form-table">
            <thead><tr><th>No.</th><th>Date</th><th>SI/OR #</th><th>Even</th><th>Particulars</th><th>Person/Place</th><th>Amount</th></tr></thead>
            <tbody>
            <?php
                $nos      = $data['item_no']        ?? [];
                $dates    = $data['item_date']      ?? [];
                $invoices = $data['invoice_number'] ?? [];
                $evens    = $data['even']           ?? [];
                $parts    = $data['particulars']    ?? [];
                $places   = $data['person_place']   ?? [];
                $amounts  = $data['amount']         ?? [];
                $count = max(count($nos), count($amounts), 1);
                for ($i = 0; $i < $count; $i++):
            ?>
                <tr>
                    <td><input type="text"   value="<?= htmlspecialchars($nos[$i]      ?? '') ?>" readonly></td>
                    <td><input type="text"   value="<?= htmlspecialchars($dates[$i]    ?? '') ?>" readonly></td>
                    <td><input type="text"   value="<?= htmlspecialchars($invoices[$i] ?? '') ?>" readonly></td>
                    <td><input type="text"   value="<?= htmlspecialchars($evens[$i]    ?? '') ?>" readonly></td>
                    <td><input type="text"   value="<?= htmlspecialchars($parts[$i]    ?? '') ?>" readonly></td>
                    <td><input type="text"   value="<?= htmlspecialchars($places[$i]   ?? '') ?>" readonly></td>
                    <td><input type="number" value="<?= htmlspecialchars($amounts[$i]  ?? '') ?>" readonly></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <div class="form-grid g-4 mt-1">
        <div class="form-group"><label>Total Amount</label><input type="number" value="<?= $ro('total_amount') ?>" readonly></div>
        <div class="form-group g-span-2"><label>Total Amount (in words)</label><input type="text" value="<?= $ro('amount_words') ?>" readonly></div>
    </div>
</div>

<div class="form-card">
    <div class="form-section-title">Submitted</div>
    <div class="form-group"><input type="text" value="<?= htmlspecialchars(date('M d, Y · g:i A', strtotime($form['created_at']))) ?>" readonly></div>
</div>
