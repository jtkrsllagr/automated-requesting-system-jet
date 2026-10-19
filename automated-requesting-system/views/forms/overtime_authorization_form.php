<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
<?php
    $isEdit = isset($form);
    $data = $data ?? [];
    $formAction = $isEdit
        ? url('forms/' . (int)$form['id'] . '/update')
        : url('forms/overtime');
    $fieldVal = fn(string $k, string $def = '') =>
        htmlspecialchars($data[$k] ?? $def);
    // hours_covered is stored as a flat interleaved array: [start1, end1, start2, end2, ...]
    $otRowCount = max(1, count((array)($data['ot_date'] ?? [])));
    $otDateVal = fn(int $i) => htmlspecialchars(($data['ot_date'][$i] ?? '') ?: date('Y-m-d'));
    $otReasonVal = fn(int $i) => htmlspecialchars($data['reason'][$i] ?? '');
    $otStartVal = fn(int $i) => htmlspecialchars($data['hours_covered'][$i * 2] ?? '');
    $otEndVal = fn(int $i) => htmlspecialchars($data['hours_covered'][$i * 2 + 1] ?? '');
    $otTotalVal = fn(int $i) => htmlspecialchars($data['hours_total'][$i] ?? '');
?>
<form method="POST" action="<?= $formAction ?>" enctype="multipart/form-data">
    <div class="page-heading">Overtime Authorization Request</div>
    <div class="page-subheading">Fill in the details below. Save as draft to continue later, or submit directly for approval.</div>
    <?= \App\Helpers\Csrf::field(); ?>

    <div class="form-card">
        <div class="form-section-title">Applicant Details</div>
        <div class="form-grid g-3">
            <div class="form-group"><label>Employee Name <span class="req">*</span></label><input type="text" name="employee_name" value="<?= $fieldVal('employee_name', $currentUser ?? '') ?>" readonly required></div>
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
            <div class="form-group"><label>Date</label><input type="date" name="request_date" required value="<?= $fieldVal('request_date') ?: date('Y-m-d') ?>"></div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-section-title">OT Request Details</div>
        <div class="table-scroll">
            <table class="form-table" id="items-table"
                data-recalc="ot"
                data-add-btn-id="add-row"
                data-total-id="total_hours"
            >
                <thead><tr><th>Date</th><th>Reason/s</th><th>Start</th><th>To</th><th>Total Hours</th><th></th></tr></thead>
                <tbody>
                    <?php for ($i = 0; $i < $otRowCount; $i++): ?>
                    <tr>
                        <td><input type="date" name="ot_date[]" required value="<?= $otDateVal($i) ?>"></td>
                        <td><input type="text" name="reason[]" value="<?= $otReasonVal($i) ?>"></td>
                        <td><input type="time" name="hours_covered[]" value="<?= $otStartVal($i) ?>"></td>
                        <td><input type="time" name="hours_covered[]" value="<?= $otEndVal($i) ?>"></td>
                        <td><input type="number" name="hours_total[]" step="any" class="ot-hours" value="<?= $otTotalVal($i) ?>"></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-row">✕</button></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-ghost btn-sm btn-add-row" id="add-row">+ Add Row</button>
        <div class="form-grid g-4 mt-1">
            <div class="form-group"><label>Total Hours Rendered</label><input type="number" name="total_hours" id="total_hours" step="0.1" readonly value="<?= $fieldVal('total_hours') ?>"></div>
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