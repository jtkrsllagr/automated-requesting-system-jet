<?php // $data and $ro() are set by show.php ?>
<div class="form-card">
    <div class="form-section-title">Applicant Details</div>
    <div class="form-grid g-4">
        <div class="form-group"><label>Applicant</label><input type="text" value="<?= $ro('employee_name') ?>" readonly></div>
        <div class="form-group"><label>Department</label><input type="text" value="<?= $ro('department') ?>" readonly></div>
        <div class="form-group"><label>Pages</label><input type="text" value="<?= $ro('page_no') ?>" readonly></div>
        <div class="form-group"><label>Date</label><input type="text" value="<?= $ro('date') ?>" readonly></div>
    </div>
    <div class="form-grid g-2 mt-1">
        <div class="form-group"><label>Project Name</label><input type="text" value="<?= $ro('project_name') ?>" readonly></div>
        <div class="form-group"><label>Project No.</label><input type="text" value="<?= $ro('project_code') ?>" readonly></div>
    </div>
</div>

<div class="form-card">
    <div class="form-section-title">Payment Details</div>
    <div class="form-grid g-4">
        <div class="form-group"><label>Transaction Code</label><input type="text" value="<?= $ro('transaction_code') ?>" readonly></div>
        <div class="form-group"><label>Type of Payment</label><input type="text" value="<?= $ro('payment_type') ?>" readonly></div>
        <div class="form-group"><label>Payee</label><input type="text" value="<?= $ro('payee') ?>" readonly></div>
        <div class="form-group"><label>Account Name</label><input type="text" value="<?= $ro('account_name') ?>" readonly></div>
        <div class="form-group"><label>Bank Name</label><input type="text" value="<?= $ro('bank_name') ?>" readonly></div>
        <div class="form-group"><label>Bank Account No.</label><input type="text" value="<?= $ro('bank_account_no') ?>" readonly></div>
        <div class="form-group"><label>Address</label><input type="text" value="<?= $ro('address') ?>" readonly></div>
    </div>
    <div class="form-group mt-1"><label>Purpose</label><textarea rows="2" readonly><?= $ro('purpose') ?></textarea></div>
</div>

<div class="form-card">
    <div class="form-section-title">Item Details</div>
    <div class="table-scroll">
        <table class="form-table">
            <thead><tr><th>Item</th><th>Description</th><th>Unit Price</th><th>Quantity</th><th>Amount</th></tr></thead>
            <tbody>
            <?php
                $items = $data['item'] ?? [];
                $descs = $data['description'] ?? [];
                $prices = $data['unit_price'] ?? [];
                $qtys = $data['quantity'] ?? [];
                $amounts = $data['amount'] ?? [];
                $count = max(count($items), 1);
                for ($i = 0; $i < $count; $i++):
            ?>
                <tr>
                    <td><input type="text" value="<?= htmlspecialchars($items[$i] ?? '') ?>" readonly></td>
                    <td><input type="text" value="<?= htmlspecialchars($descs[$i] ?? '') ?>" readonly></td>
                    <td><input type="number" value="<?= htmlspecialchars($prices[$i] ?? '') ?>" readonly></td>
                    <td><input type="number" value="<?= htmlspecialchars($qtys[$i] ?? '') ?>" readonly></td>
                    <td><input type="number" value="<?= htmlspecialchars($amounts[$i] ?? '') ?>" readonly></td>
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