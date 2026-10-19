<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
<?php
    $isEdit = isset($form);
    $data = $data ?? [];
    $formAction = $isEdit
        ? url('forms/' . (int)$form['id'] . '/update')
        : url('forms/leave/create');
    $fieldVal = fn(string $k, string $def = '') =>
        htmlspecialchars($data[$k] ?? $def);
?>
<form method="POST" action="<?= $formAction ?>" enctype="multipart/form-data">
    <div class="page-heading">Leave Application</div>
    <div class="page-subheading">Fill in the details below. Save as draft to continue later, or submit directly for approval.</div>
    <?= \App\Helpers\Csrf::field(); ?>

    <div class="form-card">
        <div class="form-section-title">Leave Details</div>
        <div class="form-grid g-4">
            <div class="form-group"><label>Name</label><input type="text" name="employee_name" value="<?= $fieldVal('employee_name', $currentUser ?? '') ?>" readonly required></div>
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
            <div class="form-group"><label>ID No.</label><input type="text" name="id_no" value="<?= $fieldVal('id_no') ?>"></div>
            <div class="form-group"><label>Date Filed</label><input type="date" name="date" required value="<?= $fieldVal('date') ?: date('Y-m-d') ?>"></div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-section-title">Leave Period</div>
        <div class="form-grid g-3">
            <div class="form-group"><label>From</label><input type="date" name="from_date" value="<?= $fieldVal('from_date') ?: date('Y-m-d') ?>"></div>
            <div class="form-group"><label>To</label><input type="date" name="to_date" value="<?= $fieldVal('to_date') ?: date('Y-m-d') ?>"></div>
            <div class="form-group"><label>Number of Leave Days</label><input type="number" step="any" name="num_of_leave" value="<?= $fieldVal('num_of_leave') ?>"></div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-section-title">Leave Description</div>
        <div class="form-grid g-4">
            <div class="form-group">
                <label>Type of Leave</label>
                <select name="leave_type" required>
                    <option value="">Select type</option>
                    <option value="vacation" <?= ($data['leave_type'] ?? '') === 'vacation' ? 'selected' : '' ?>>Vacation</option>
                    <option value="sick" <?= ($data['leave_type'] ?? '') === 'sick' ? 'selected' : '' ?>>Sick</option>
                    <option value="parental" <?= ($data['leave_type'] ?? '') === 'parental' ? 'selected' : '' ?>>Parental</option>
                    <option value="other" <?= ($data['leave_type'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>In case of Vacation</label>
                <select name="vacation_leave">
                    <option value="">Select Location</option>
                    <option value="local" <?= ($data['vacation_leave'] ?? '') === 'local' ? 'selected' : '' ?>>Local</option>
                    <option value="abroad" <?= ($data['vacation_leave'] ?? '') === 'abroad' ? 'selected' : '' ?>>Abroad</option>
                </select>
            </div>
            <div class="form-group">
                <label>In case of Sick</label>
                <select name="sick_leave">
                    <option value="">Select Recovery</option>
                    <option value="hospital" <?= ($data['sick_leave'] ?? '') === 'hospital' ? 'selected' : '' ?>>Hospital</option>
                    <option value="out_patient" <?= ($data['sick_leave'] ?? '') === 'out_patient' ? 'selected' : '' ?>>Out Patient</option>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Term</label>
                <select name="payment_term" required>
                    <option value="">Select Pay Option</option>
                    <option value="paid" <?= ($data['payment_term'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid Leave</option>
                    <option value="unpaid" <?= ($data['payment_term'] ?? '') === 'unpaid' ? 'selected' : '' ?>>Unpaid Leave</option>
                </select>
            </div>
        </div>
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
                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    $name = htmlspecialchars(basename($f));
                    $url = htmlspecialchars(url($f));
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