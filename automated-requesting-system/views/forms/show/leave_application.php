<?php // $data and $ro() are set by show.php ?>
<div class="form-card">
    <div class="form-section-title">Leave Details</div>
    <div class="form-grid g-4">
        <div class="form-group"><label>Name</label><input type="text" value="<?= $ro('employee_name') ?>" readonly></div>
        <div class="form-group"><label>Department</label><input type="text" value="<?= $ro('department') ?>" readonly></div>
        <div class="form-group"><label>ID No.</label><input type="text" value="<?= $ro('id_no') ?>" readonly></div>
        <div class="form-group"><label>Date Filed</label><input type="text" value="<?= $ro('date') ?>" readonly></div>
    </div>
</div>

<div class="form-card">
    <div class="form-section-title">Leave Period</div>
    <div class="form-grid g-3">
        <div class="form-group"><label>From</label><input type="text" value="<?= $ro('from_date') ?>" readonly></div>
        <div class="form-group"><label>To</label><input type="text" value="<?= $ro('to_date') ?>" readonly></div>
        <div class="form-group"><label>Number of Leave Days</label><input type="text" value="<?= $ro('num_of_leave') ?>" readonly></div>
    </div>
</div>

<div class="form-card">
    <div class="form-section-title">Leave Description</div>
    <div class="form-grid g-4">
        <div class="form-group"><label>Type of Leave</label><input type="text" value="<?= $ro('leave_type') ?>" readonly></div>
        <div class="form-group"><label>In case of Vacation</label><input type="text" value="<?= $ro('vacation_leave') ?>" readonly></div>
        <div class="form-group"><label>In case of Sick</label><input type="text" value="<?= $ro('sick_leave') ?>" readonly></div>
        <div class="form-group"><label>Payment Term</label><input type="text" value="<?= $ro('payment_term') ?>" readonly></div>
    </div>
</div>

<div class="form-card">
    <div class="form-section-title">Submitted</div>
    <div class="form-group"><input type="text" value="<?= htmlspecialchars(date('M d, Y · g:i A', strtotime($form['created_at']))) ?>" readonly></div>
</div>
