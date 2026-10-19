<?php // $data and $ro() are set by show.php ?>
<div class="form-card">
    <div class="form-section-title">Applicant Details</div>
    <div class="form-grid g-4">
        <div class="form-group"><label>Car / Plate Number</label><input type="text" value="<?= $ro('car_available') ?>" readonly></div>
        <div class="form-group"><label>Date</label><input type="text" value="<?= $ro('date') ?>" readonly></div>
        <div class="form-group"><label>Applicant</label><input type="text" value="<?= $ro('employee_name') ?>" readonly></div>
        <div class="form-group"><label>Department</label><input type="text" value="<?= $ro('department') ?>" readonly></div>
        <div class="form-group"><label>Total Mileage</label><input type="text" value="<?= $ro('total_mileage') ?>" readonly></div>
        <div class="form-group"><label>Schedule Time</label><input type="text" value="<?= $ro('schedule_time') ?>" readonly></div>
        <div class="form-group"><label>Type of Trip</label><input type="text" value="<?= $ro('trip_type') ?>" readonly></div>
    </div>
</div>

<div class="form-card">
    <div class="form-section-title">Destination Details</div>
    <?php for ($i = 1; $i <= 4; $i++): ?>
    <div class="form-grid g-2 mt-1">
        <div class="form-group"><label>Destination <?= $i ?></label><input type="text" value="<?= $ro('destination_' . $i) ?>" readonly></div>
        <div class="form-group"><label>Purpose <?= $i ?></label><input type="text" value="<?= $ro('purpose_' . $i) ?>" readonly></div>
    </div>
    <?php endfor; ?>
    <div class="form-group mt-1"><label>Notes</label><input type="text" value="<?= $ro('notes') ?>" readonly></div>
</div>

<div class="form-card">
    <div class="form-section-title">Submitted</div>
    <div class="form-group"><input type="text" value="<?= htmlspecialchars(date('M d, Y · g:i A', strtotime($form['created_at']))) ?>" readonly></div>
</div>
