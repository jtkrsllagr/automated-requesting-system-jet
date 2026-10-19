<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
<?php
    $isEdit = isset($form);
    $data = $data ?? [];
    $formAction = $isEdit
        ? url('forms/' . (int)$form['id'] . '/update')
        : url('forms/liquidation');
    $fieldVal = fn(string $k, string $def = '') =>
        htmlspecialchars($data[$k] ?? $def);
    // Table rows are stored as parallel arrays (e.g. $data['item_no'][0..n]).
    // Render one <tr> per saved row (min 1) instead of a single hardcoded row.
    $rowVal = fn(string $k, int $i, string $def = '') =>
        htmlspecialchars($data[$k][$i] ?? $def);
    $liqRowKeys = ['item_no', 'item_date', 'invoice_number', 'even', 'particulars', 'person_place', 'amount'];
    $liqRowCount = 1;
    foreach ($liqRowKeys as $rk) {
        $liqRowCount = max($liqRowCount, count((array)($data[$rk] ?? [])));
    }
?>
<form method="POST" action="<?= $formAction ?>" enctype="multipart/form-data">
    <div class="page-heading">Liquidation Request</div>
    <div class="page-subheading">Fill in the details below. Save as draft to continue later, or submit directly for approval.</div>
    <?= \App\Helpers\Csrf::field(); ?>

    <div class="form-card">
        <div class="form-section-title">Applicant Details</div>
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
            <div class="form-group"><label>Pages</label><input type="text" name="page_no" placeholder="No. of attachments" value="<?= $fieldVal('page_no') ?>"></div>
            <div class="form-group"><label>Date</label><input type="date" name="request_date" required value="<?= $fieldVal('request_date') ?: date('Y-m-d') ?>"></div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-section-title">Advance Details</div>
        <div class="form-grid g-3">
            <div class="form-group"><label>Advance Date</label><input type="date" name="advance_date" value="<?= $fieldVal('advance_date') ?: date('Y-m-d') ?>"></div>
            <div class="form-group"><label>Advance Type</label><input type="text" name="advance_type" value="<?= $fieldVal('advance_type') ?>"></div>
            <div class="form-group"><label>Advance Amount</label><input type="number" step="any" name="advance_amount" id="advance_amount" value="<?= $fieldVal('advance_amount') ?>"></div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-section-title">Expense Details</div>
        <div class="table-scroll">
            <table class="form-table" id="liquidation-table"
                data-recalc="amount-only"
                data-add-btn-id="add-row"
                data-total-id="total_amount"
                data-balance-id="balance"
                data-advance-id="advance_amount"
            >
                <thead><tr><th>No.</th><th>Date</th><th>SI/OR #</th><th>Even</th><th>Particulars</th><th>Person/Place</th><th>Amount</th><th></th></tr></thead>
                <tbody>
                    <?php for ($i = 0; $i < $liqRowCount; $i++): ?>
                    <tr>
                        <td><input type="number" step="any" name="item_no[]" value="<?= $rowVal('item_no', $i) ?>"></td>
                        <td><input type="date" name="item_date[]" value="<?= $rowVal('item_date', $i) ?: date('Y-m-d') ?>"></td>
                        <td><input type="text" name="invoice_number[]" value="<?= $rowVal('invoice_number', $i) ?>"></td>
                        <td><input type="text" name="even[]" value="<?= $rowVal('even', $i) ?>"></td>
                        <td><input type="text" name="particulars[]" value="<?= $rowVal('particulars', $i) ?>"></td>
                        <td><input type="text" name="person_place[]" value="<?= $rowVal('person_place', $i) ?>"></td>
                        <td><input type="number" step="any" name="amount[]" class="row-amount" value="<?= $rowVal('amount', $i) ?>"></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-row">✕</button></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-ghost btn-sm btn-add-row" id="add-row">+ Add Row</button>
        <div class="form-grid g-4 mt-1">
            <div class="form-group"><label>Total Amount</label><input type="number" step="0.01" name="total_amount" id="total_amount" readonly value="<?= $fieldVal('total_amount') ?>"></div>
            <div class="form-group"><label>Balance / Refund</label><input type="number" step="0.01" name="balance" id="balance" readonly value="<?= $fieldVal('balance') ?>"></div>
            <div class="form-group g-span-2"><label>Total Amount (in words)</label><input type="text" name="amount_words" value="<?= $fieldVal('amount_words') ?>"></div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-section-title">Supporting Documents</div>

        <label class="attach-drop" id="attachDrop">
            <i class="ti ti-cloud-upload"></i>
            <span class="attach-drop-main">Choose files here</span>
            <span class="attach-drop-sub">PDF, JPG, PNG — max 20 MB each · multiple allowed</span>
            <input type="file" name="attachments[]" id="attachInput"
                multiple accept=".pdf,.jpg,.jpeg,.png" class="hidden-input">
        </label>

        <div id="attachNewList" class="attach-list"></div>

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