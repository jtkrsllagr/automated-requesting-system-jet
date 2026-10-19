<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
<?php
    $isEdit = isset($form);
    $data = $data ?? [];
    $formAction = $isEdit
        ? url('forms/' . (int)$form['id'] . '/update')
        : url('forms/advance-payment');
    $fieldVal = fn(string $k, string $def = '') =>
        htmlspecialchars($data[$k] ?? $def);
    $rowVal = fn(string $k, int $i, string $def = '') =>
        htmlspecialchars($data[$k][$i] ?? $def);
    $itemRowKeys = ['item', 'description', 'unit_price', 'quantity', 'amount'];
    $itemRowCount = 1;
    foreach ($itemRowKeys as $rk) {
        $itemRowCount = max($itemRowCount, count((array)($data[$rk] ?? [])));
    }
?>
<form method="POST" action="<?= $formAction ?>" enctype="multipart/form-data">
    <div class="page-heading">Advance Payment Request</div>
    <div class="page-subheading">Fill in the details below. Save as draft to continue later, or submit directly for approval.</div>

    <?= \App\Helpers\Csrf::field(); ?>

    <div class="form-card">
        <div class="form-section-title">Applicant Details</div>
        <div class="form-grid g-4">
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
            <div class="form-group"><label>Pages</label><input type="text" name="page_no" placeholder="No. of attachments" value="<?= $fieldVal('page_no') ?>"></div>
            <div class="form-group"><label>Date</label><input type="date" name="date" required value="<?= $fieldVal('date') ?: date('Y-m-d') ?>"></div>
        </div>
        <div class="form-grid g-2 mt-1">
            <div class="form-group"><label>Project Name</label><input type="text" name="project_name" value="<?= $fieldVal('project_name') ?>"></div>
            <div class="form-group"><label>Project No.</label><input type="text" name="project_code" value="<?= $fieldVal('project_code') ?>"></div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-section-title">Payment Details</div>
        <div class="form-grid g-4">
            <div class="form-group">
                <label>Type of Payment</label>
                <select name="payment_type" required>
                    <option value="">-- Select --</option>
                    <option <?= $fieldVal('payment_type') === 'Cash' ? 'selected' : '' ?>>Cash</option>
                    <option <?= $fieldVal('payment_type') === 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option <?= $fieldVal('payment_type') === 'Cheque' ? 'selected' : '' ?>>Cheque</option>
                </select>
            </div>
            <div class="form-group"><label>Payee</label><input type="text" name="payee" required value="<?= $fieldVal('payee') ?>"></div>
            <div class="form-group"><label>Account Name</label><input type="text" name="account_name" value="<?= $fieldVal('account_name') ?>"></div>
            <div class="form-group"><label>Bank Name</label><input type="text" name="bank_name" value="<?= $fieldVal('bank_name') ?>"></div>
            <div class="form-group"><label>Bank Account No.</label><input type="text" name="bank_account_no" value="<?= $fieldVal('bank_account_no') ?>"></div>
            <div class="form-group"><label>Address</label><input type="text" name="address" value="<?= $fieldVal('address') ?>"></div>
        </div>
        <div class="form-group mt-1">
            <label>Purpose</label><textarea name="purpose" rows="2"><?= $fieldVal('purpose') ?></textarea>
        </div>
    </div>

    <div class="form-card">
        <div class="form-section-title">Item Details</div>
        <div class="table-scroll">
            <table class="form-table" id="items-table"
                data-recalc="items"
                data-add-btn-id="add-row"
                data-total-id="total_amount"
            >
                <thead><tr><th>Item</th><th>Description</th><th>Unit Price</th><th>Quantity</th><th>Amount</th><th></th></tr></thead>
                <tbody>
                    <?php for ($i = 0; $i < $itemRowCount; $i++): ?>
                    <tr>
                        <td><input type="text" name="item[]" value="<?= $rowVal('item', $i) ?>"></td>
                        <td><input type="text" name="description[]" value="<?= $rowVal('description', $i) ?>"></td>
                        <td><input type="number" step="any" name="unit_price[]" class="unit-price" value="<?= $rowVal('unit_price', $i) ?>"></td>
                        <td><input type="number" step="any" name="quantity[]" class="qty" value="<?= $rowVal('quantity', $i) ?>"></td>
                        <td><input type="number" step="0.01" name="amount[]" class="row-amount" readonly value="<?= $rowVal('amount', $i) ?>"></td>
                        <td><button type="button" class="btn btn-danger btn-sm remove-row">✕</button></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-ghost btn-sm btn-add-row" id="add-row">+ Add Row</button>
        <div class="form-grid g-4 mt-1">
            <div class="form-group"><label>Total Amount</label><input type="number" step="0.01" name="total_amount" id="total_amount" readonly value="<?= $fieldVal('total_amount') ?>"></div>
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