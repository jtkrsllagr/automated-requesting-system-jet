<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
<?php
    $isEdit = isset($form);
    $data = $data ?? [];
    $formAction = $isEdit
        ? url('forms/' . (int)$form['id'] . '/update')
        : url('forms/vehicle-request/create');
    $fieldVal = fn(string $k, string $def = '') =>
        htmlspecialchars($data[$k] ?? $def);
?>
<form method="POST" action="<?= $formAction ?>" enctype="multipart/form-data">
    <div class="page-heading">Vehicle Request</div>
    <div class="page-subheading">Fill in the details below. Save as draft to continue later, or submit directly for approval.</div>
    <?= \App\Helpers\Csrf::field(); ?>

    <div class="form-card">
        <div class="form-section-title">Applicant Details</div>
        <div class="form-grid g-4">
            <div class="form-group"><label>Car / Plate Number</label><input type="text" name="car_available" required value="<?= $fieldVal('car_available') ?>"></div>
            <div class="form-group"><label>Date</label><input type="date" name="date" required value="<?= $fieldVal('date') ?: date('Y-m-d') ?>"></div>
            <div class="form-group"><label>Applicant</label><input type="text" name="employee_name" value="<?= $fieldVal('employee_name', $currentUser ?? '') ?>" readonly required></div>
            <div class="form-group">
                <label>Department</label>
                <div class="input-select">
                    <input type="text" name="department" list="dept-list" autocomplete="off" required value="<?= $fieldVal('department', $currentDept ?? '') ?>">
                    <datalist id="dept-list">
                        <?php foreach ($departments ?? [] as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            <div class="form-group"><label>Total Mileage</label><input type="number" step="any" name="total_mileage" value="<?= $fieldVal('total_mileage') ?>"></div>
            <div class="form-group"><label>Schedule Time</label><input type="text" name="schedule_time" placeholder="Departure and arrival time" value="<?= $fieldVal('schedule_time') ?>"></div>
            <div class="form-group">
                <label>Type of Trip</label>
                <select name="trip_type" required>
                    <option value="">-- Select --</option>
                    <option value="journey" <?= ($data['trip_type'] ?? '') === 'journey' ? 'selected' : '' ?>>Journey</option>
                    <option value="round" <?= ($data['trip_type'] ?? '') === 'round' ? 'selected' : '' ?>>Round Trip</option>
                    <option value="single" <?= ($data['trip_type'] ?? '') === 'single' ? 'selected' : '' ?>>Single</option>
                </select>
            </div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-section-title">Destination Details</div>
        <?php for ($i = 1; $i <= 4; $i++): ?>
        <div class="form-grid g-2 mt-1">
            <div class="form-group"><label>Destination <?= $i ?></label><input type="text" name="destination_<?= $i ?>" value="<?= $fieldVal('destination_' . $i) ?>"></div>
            <div class="form-group"><label>Purpose <?= $i ?></label><input type="text" name="purpose_<?= $i ?>" value="<?= $fieldVal('purpose_' . $i) ?>"></div>
        </div>
        <?php endfor; ?>
        <div class="form-group mt-1"><label>Notes</label><input type="text" name="notes" value="<?= $fieldVal('notes') ?>"></div>
    </div>

    <div class="form-card">
        <div class="form-section-title">Supporting Documents</div>

        <!-- Drop zone -->
        <label class="attach-drop" id="attachDrop">
            <i class="ti ti-cloud-upload"></i>
            <span class="attach-drop-main">Choose files here</span>
            <span class="attach-drop-sub">PDF, JPG, PNG — max 20 MB each · multiple allowed</span>
            <input type="file" name="attachments[]" id="attachInput"
                multiple accept=".pdf,.jpg,.jpeg,.png" class="hidden-input">
        </label>

        <!-- Newly picked files (pre-upload preview) -->
        <div id="attachNewList" class="attach-list"></div>

        <!-- Already-saved files (edit mode) -->
        <?php if (!empty($data['attachments'])): ?>
            <div class="attach-saved-label">Attached files</div>
            <div class="attach-list" id="attachSavedList">
                <?php foreach ((array)$data['attachments'] as $i => $f):
                    $ext  = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    $name = htmlspecialchars(basename($f));
                    $url  = htmlspecialchars(url($f));
                    $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                ?>
                <div class="attach-item" id="saved-<?= $i ?>">
                    <?php if ($isImg): ?>
                        <img src="<?= $url ?>" class="attach-thumb" alt="<?= $name ?>">
                    <?php else: ?>
                        <span class="attach-icon"><i class="ti ti-file-type-pdf"></i></span>
                    <?php endif; ?>
                    <div class="attach-info">
                        <a href="<?= $url ?>" target="_blank" class="attach-name"><?= $name ?></a>
                    </div>
                    <div class="attach-actions">
                        <a href="<?= $url ?>" download class="attach-btn" title="Download">
                            <i class="ti ti-download"></i>
                        </a>
                        <a href="<?= $url ?>" target="_blank" class="attach-btn" title="View">
                            <i class="ti ti-eye"></i>
                        </a>
                        <button type="button" class="attach-btn attach-btn--danger"
                                title="Remove" data-remove-saved="<?= $i ?>">
                            <i class="ti ti-trash"></i>
                        </button>
                        <!-- hidden input keeps the path unless removed -->
                        <input type="hidden" name="existing_attachments[]" value="<?= htmlspecialchars($f) ?>">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary">Submit</button>
    <?php if (($form['status'] ?? '') !== 'submitted'): ?>
        <button type="submit" name="save_draft" value="1" class="btn btn-light">Save as Draft</button>
    <?php endif; ?>
</form>