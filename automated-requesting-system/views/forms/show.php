<?php
    $formLabel = \App\Helpers\FormLabels::all();
    $statusBadge = \App\Helpers\FormLabels::allBadges();
    $stepBadge = [ 'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger' ];

    $type = $form['form_type'] ?? 'unknown';
    $title = \App\Helpers\FormLabels::get($type);
    $roleId = $_SESSION['role_id'];
    $formId = $form['id'];
    $isFinalHrDepartmentRequest = in_array($type, ['leave_application', 'overtime_authorization'], true)
        && preg_match(
            '/\b(management|human\s+resources|hr|admin(?:istration)?)\b/i',
            (string) ($form['submitter_department'] ?? '')
        ) === 1;
    $isSupervisorFinalHrDepartmentRequest = in_array($type, ['leave_application', 'overtime_authorization'], true)
        && preg_match(
            '/\b(accounting|information\s+technology|it|finance|logistics)\b|(?<![a-z-])sales\b|\bpre-sales\s*\(engineer\)|\bpost-sales\s*\(engineer\)/i',
            (string) ($form['submitter_department'] ?? '')
        ) === 1;
    $humanStatus = \App\Helpers\FormLabels::statusLabel($form['status']);
    $formData = json_decode((string) ($form['data'] ?? '[]'), true);
    $formData = is_array($formData) ? $formData : [];
    $hasPaymentProof = !empty($formData['payment_proof_path']) || !empty($formData['payment_proof_attachments']);
    $hasLiquidationProof = !empty($formData['liquidation_proof_path']) || !empty($formData['liquidation_proof_attachments']);

    $pendingTrailSequences = [];
    foreach (($approvalSteps ?? []) as $approvalStep) {
        if (($approvalStep['status'] ?? '') === 'pending') {
            $pendingTrailSequences[] = (int) $approvalStep['sequence'];
        }
    }
    sort($pendingTrailSequences);

    if (($form['status'] ?? '') === 'rejected') {
        $trailStatusLabel = 'Rejected';
        $trailStatusBadge = 'danger';
    } elseif (!empty($pendingTrailSequences)) {
        $trailStatusLabel = (
            ($isFinalHrDepartmentRequest && $pendingTrailSequences[0] === 2)
            || ($isSupervisorFinalHrDepartmentRequest && $pendingTrailSequences[0] === 3)
        )
            ? 'Final Authorization'
            : \App\Helpers\FormLabels::stepLabel($pendingTrailSequences[0], $type);
        $trailStatusBadge = 'warning';
    } elseif (($form['status'] ?? '') === 'draft') {
        $trailStatusLabel = 'Draft';
        $trailStatusBadge = 'secondary';
    } elseif (
        ($form['status'] ?? '') === 'completed'
        && in_array($type, ['leave_application', 'overtime_authorization'], true)
    ) {
        $trailStatusLabel = 'Successfully Processed';
        $trailStatusBadge = 'success';
    } elseif (!$hasPaymentProof) {
        $trailStatusLabel = 'Payment Release Proof';
        $trailStatusBadge = 'warning';
    } elseif ($type !== 'reimbursement' && !$hasLiquidationProof) {
        $trailStatusLabel = 'Liquidation Document';
        $trailStatusBadge = 'warning';
    } else {
        $trailStatusLabel = 'Completed';
        $trailStatusBadge = 'success';
    }

    $isOwner = ((int)$form['submitted_by'] === (int)$_SESSION['user_id']) || $roleId == 1;
    $editableStatuses = ['draft', 'submitted', 'rejected'];
    // $canEdit is computed by the controller (owner-in-editable-status, OR
    // AcquisitionChecker while the form is at their Process stage).
    $canDelete = $isOwner && in_array($form['status'], $editableStatuses, true);

    $submittedBy = $form['submitter_name'] ?? ($form['submitted_by_name'] ?? null);
    $submittedDate = !empty($form['created_at']) ? date('M j, Y', strtotime($form['created_at'])) : null;
?>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="page-header show-page-header">

    <div class="show-identity">
        <button id="btn-back" class="btn btn-ghost btn-sm show-back-btn" data-fallback-url="<?= url('approvals') ?>" title="Go back">
            <i class="ti ti-arrow-left"></i>
        </button>
        <div class="show-title-block">
            <div class="show-form-title">
                <?= htmlspecialchars($title) ?>
                <span class="show-form-id">#<?= $formId ?></span>
            </div>
            <?php if ($submittedBy || $submittedDate): ?>
            <div class="show-form-meta">
                <?php if ($submittedBy): ?>
                    <i class="ti ti-user"></i> <?= htmlspecialchars($submittedBy) ?>
                <?php endif; ?>
                <?php if ($submittedDate): ?>
                    <span class="show-meta-sep">·</span>
                    <i class="ti ti-calendar"></i> <?= $submittedDate ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <span class="badge badge-<?= htmlspecialchars($trailStatusBadge) ?> show-status-badge">
            <?= htmlspecialchars($trailStatusLabel) ?>
        </span>
    </div>

    <div class="show-actions">
        <button type="button" class="btn btn-secondary btn-sm" id="btn-share-trigger" data-modal="shareModal">
            <i class="ti ti-share"></i> Share
        </button>
        <?php if ($canEdit): ?>
        <a href="<?= url('forms/' . $formId . '/edit') ?>" class="btn btn-secondary btn-sm">
            <i class="ti ti-pencil"></i> Edit
        </a>
        <?php endif; ?>
        <?php if ($canDelete): ?>
        <form method="POST" action="<?= url('forms/' . $formId . '/delete') ?>" id="deleteForm">
            <?= \App\Helpers\Csrf::field() ?>
            <button type="button" class="btn btn-danger btn-sm" id="btn-delete-trigger"
                data-modal="deleteModal"
                data-form-title="<?= htmlspecialchars($title . ' #' . $formId) ?>">
                <i class="ti ti-trash"></i> Delete
            </button>
        </form>
        <?php endif; ?>
    </div>

</div>

<!-- Share modal -->
<div class="ars-modal-backdrop" id="shareModal" role="dialog" aria-modal="true" aria-labelledby="shareModalTitle" hidden>
    <div class="ars-modal">
        <div class="ars-modal-icon"><i class="ti ti-share"></i></div>
        <h3 class="ars-modal-title" id="shareModalTitle">Share this form</h3>
        <p class="ars-modal-body">
            Send <strong><?= htmlspecialchars($title . ' #' . $formId) ?></strong> to a coworker via Messaging.
        </p>
        <div class="form-group">
            <label class="show-field-label">Send to</label>
            <input type="text" id="shareRecipientSearch" placeholder="Search people…" autocomplete="off">
            <div id="shareRecipientList" class="share-recipient-list"></div>
        </div>
        <div class="form-group">
            <label class="show-field-label">Note <span class="show-field-hint">optional</span></label>
            <textarea id="shareNote" rows="2" placeholder="Add a message…"></textarea>
        </div>
        <div id="shareModalError" class="alert alert-danger" hidden></div>
        <div class="ars-modal-actions">
            <button type="button" class="btn btn-ghost btn-sm" data-modal-close="shareModal">Cancel</button>
            <button type="button" class="btn btn-primary btn-sm" id="btn-share-confirm" disabled>
                <i class="ti ti-send"></i> Share
            </button>
        </div>
    </div>
</div>

<?php if ($canDelete): ?>
<div class="ars-modal-backdrop" id="deleteModal" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle" hidden>
    <div class="ars-modal">
        <div class="ars-modal-icon ars-modal-icon--danger"><i class="ti ti-trash"></i></div>
        <h3 class="ars-modal-title" id="deleteModalTitle">Delete Form?</h3>
        <p class="ars-modal-body">
            You're about to permanently delete <strong id="deleteModalFormName"></strong>. This action cannot be undone.
        </p>
        <div class="ars-modal-actions">
            <button type="button" class="btn btn-ghost btn-sm" data-modal-close="deleteModal">Cancel</button>
            <button type="button" class="btn btn-danger btn-sm" id="btn-delete-confirm">
                <i class="ti ti-trash"></i> Yes, Delete
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="two-col">

    <!-- ── Left: Form Details ── -->
    <div class="card show-details-card">
        <div class="card-header card-header--flex">
            <i class="ti ti-file-description"></i>
            Form Details
        </div>
        <div class="card-body">
            <?php if (empty($data)): ?>
                <div class="empty-state">
                    <i class="ti ti-file-off empty-state-icon"></i>
                    <p>No form data available.</p>
                </div>
            <?php else:
                // Helper available to all partials
                $isAssignedImmediateHead = false;
                foreach (($approvalSteps ?? []) as $approvalStep) {
                    if ((int) ($approvalStep['sequence'] ?? 0) === 2
                        && (int) ($approvalStep['approver_id'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0)) {
                        $isAssignedImmediateHead = true;
                        break;
                    }
                }
                $canViewFullBankAccount = in_array((int) $roleId, [2, 5, 6], true)
                    || $isAssignedImmediateHead;
                $ro = function (string $k, string $def = '') use ($data, $canViewFullBankAccount): string {
                    $value = (string) ($data[$k] ?? $def);
                    if ($k === 'bank_account_no' && !$canViewFullBankAccount) {
                        $digits = preg_replace('/\D+/', '', $value);
                        $value = strlen($digits) > 4
                            ? str_repeat('*', strlen($digits) - 4) . substr($digits, -4)
                            : $digits;
                    }
                    return htmlspecialchars($value);
                };
                $partial = __DIR__ . '/show/' . $type . '.php';
                if (file_exists($partial)) {
                    require $partial; 
                } else {
                    require __DIR__ . '/show/_fallback.php';
                }

                // ── Attachments ──
                if (!empty($data['attachments'])): ?>
                    <div class="show-dl-row">
                        <span class="show-dl-label">Attachments</span>
                        <div class="show-dl-value">
                            <div class="attach-list">
                                <?php foreach ((array)$data['attachments'] as $f):
                                    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                                    $name = htmlspecialchars(basename($f));
                                    $url = htmlspecialchars(url($f));
                                    $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                                ?>
                                <div class="attach-item">
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
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif;
            endif; ?>
        </div>
    </div>

    <!-- ── Right column ── -->
    <div class="show-right-col">

        <div class="card">
            <div class="card-header card-header--flex">
                <i class="ti ti-timeline"></i>
                Approval Trail
            </div>
            <div class="card-body">
                <?php require __DIR__ . '/pipeline_stepper.php'; ?>
            </div>
        </div>

        <?php if ($canAct): ?>
        <div class="card card-action show-action-card">
            <div class="card-header card-header--flex">
                <i class="ti ti-writing"></i>
                Your Action
            </div>
            <div class="card-body">
                <?php
                    $actionLabels = [
                        'submit' => 'Submit for Approval',
                        'checker-approval' => 'Approve',
                        'hr-verification' => 'Approve',
                        'review-approval' => 'Approve',
                        'process-approval' => 'Approve',
                        'evaluation-approval' => 'Approve',
                        'grant-approval' => 'Approve & Complete',
                    ];
                    $approveLabel = $actionLabels[$nextAction] ?? 'Approve';
                    $isAdminForm = in_array($type, ['overtime_authorization', 'leave_application', 'vehicle_request'], true);
                    $isReimbursementOrLiquidation = in_array($type, ['reimbursement', 'liquidation'], true);
                    $nextStepHints = [];
                    if ($nextAction === 'submit') {
                        $nextStepHints['submit'] = $type === 'vehicle_request'
                            ? 'Sends this to the Supervisor for verification, when assigned.'
                            : 'Sends this to the Supervisor for verification.';
                    } elseif ($nextAction === 'checker-approval') {
                        $nextStepHints['checker-approval'] = $isAdminForm
                            ? ($type === 'vehicle_request'
                                ? 'Forwards to Admin Evaluation.'
                                : 'Forwards to the Department Head for review.')
                            : ($type === 'reimbursement'
                                ? 'Forwards to HR for verification.'
                                : 'Forwards to Accounting for validation.');
                    } elseif ($nextAction === 'hr-verification') {
                        $nextStepHints['hr-verification'] = 'Forwards to Accounting for validation.';
                    } elseif ($nextAction === 'review-approval') {
                        $nextStepHints['review-approval'] = $type === 'vehicle_request'
                            ? 'Forwards to Final Admin Approval.'
                            : 'Forwards to the Final Approver for authorization.';
                    } elseif ($nextAction === 'process-approval') {
                        $nextStepHints['process-approval'] = $isReimbursementOrLiquidation
                            ? ($type === 'reimbursement'
                                ? 'Forwards to Financial Assessment.'
                                : 'Requires both HR and Accounting verification before advancing to Financial Assessment.')
                            : 'Forwards to the Finance Head for financial assessment.';
                    } elseif ($nextAction === 'evaluation-approval') {
                        $nextStepHints['evaluation-approval'] = 'Forwards to the Final Approver for authorization.'; 
                    } elseif ($nextAction === 'grant-approval') {
                        $nextStepHints['grant-approval'] = 'Finalizes and completes this request.';
                    }
                ?> 
                <form method="POST" id="approvalForm" enctype="multipart/form-data">
                    <?= \App\Helpers\Csrf::field(); ?>
                    <?php if (isset($nextStepHints[$nextAction])): ?>
                    <div class="show-next-hint">
                        <i class="ti ti-arrow-right"></i>
                        <?= htmlspecialchars($nextStepHints[$nextAction]) ?>
                    </div>
                    <?php endif; ?>
                    <div class="form-group show-remarks-group">
                        <label class="show-field-label">
                            Remarks
                            <?php if ($nextAction !== 'submit'): ?>
                                <span class="show-field-hint" id="remarks-hint"></span>
                            <?php endif; ?>
                        </label>
                        <textarea name="remarks" rows="3" id="remarksField" placeholder="Add a note (To reject this request, a reason must be provided)"></textarea>
                    </div>
                    <?php if ($nextAction === 'process-approval'
                        && in_array($type, ['advance_payment', 'request_for_payment', 'reimbursement', 'liquidation'], true)
                        && in_array((int)$roleId, [1, 5], true)): ?>
                    <div class="form-group">
                        <label class="show-field-label" for="checkVoucherField">
                            Check Voucher
                            <span class="show-field-hint">required</span>
                        </label>
                        <input type="text" name="check_voucher" id="checkVoucherField"
                            maxlength="100" required autocomplete="off" 
                            placeholder="Enter Check Voucher">
                    </div>
                    <?php endif; ?>
                    <?php if ($nextAction === 'process-approval'
                        && (int)$roleId === 5
                        && in_array($type, ['advance_payment', 'request_for_payment', 'reimbursement'], true)): ?>
                    <div class="form-group">
                        <label class="show-field-label" for="approvedAmountField">
                            Approved Amount
                            <span class="show-field-hint">required</span>
                        </label>
                        <input type="number" name="approved_amount" id="approvedAmountField"
                            min="0" max="9999999999999.99" step="0.01" required autocomplete="off"
                            placeholder="Enter Approved Amount">
                    </div>
                    <?php endif; ?>
                    <div class="form-group show-attach-group">
                        <label class="show-field-label">
                            Signature
                            <span class="show-field-hint">optional — draw or upload</span>
                        </label>
                        <div class="sig-tabs" role="tablist">
                            <button type="button" class="sig-tab active" id="sigTabDraw" role="tab" aria-selected="true">
                                <i class="ti ti-signature"></i> Draw
                            </button>
                            <button type="button" class="sig-tab" id="sigTabUpload" role="tab" aria-selected="false">
                                <i class="ti ti-paperclip"></i> Upload
                            </button>
                        </div>
                        <div class="sig-panel" id="sigPanelDraw" role="tabpanel">
                            <div class="sig-pad-wrap" id="sigPadWrap">
                                <span class="sig-placeholder" id="sigPlaceholder">Sign here</span>
                                <canvas class="sig-canvas" id="sigCanvas"></canvas>
                                <span class="sig-baseline"></span>
                            </div>
                            <div class="sig-pad-toolbar">
                                <span class="sig-pad-status" id="sigStatus">
                                    <i class="ti ti-info-circle"></i> Draw with your mouse or finger
                                </span>
                                <button type="button" class="btn btn-ghost btn-sm" id="sigClear">
                                    <i class="ti ti-eraser"></i> Clear
                                </button>
                            </div>
                        </div>
                        <div class="sig-panel sig-hidden" id="sigPanelUpload" role="tabpanel">
                            <label class="show-file-drop" id="fileDrop">
                                <i class="ti ti-upload"></i>
                                <span id="fileDropLabel">Drag & drop, or click to attach</span>
                                <span class="show-file-drop-sub">Image or PDF, up to 5&nbsp;MB</span>
                                <input type="file" name="approval_file"
                                    accept="image/jpeg,image/png,image/gif,application/pdf"
                                    class="hidden-input" id="approvalFile">
                            </label>
                            <div class="file-preview sig-hidden" id="filePreview">
                                <img class="file-preview-thumb sig-hidden" id="filePreviewImg" alt="">
                                <i class="ti ti-file-type-pdf file-preview-icon sig-hidden" id="filePreviewPdfIcon"></i>
                                <div class="file-preview-info">
                                    <span class="file-preview-name" id="filePreviewName"></span>
                                    <span class="file-preview-size" id="filePreviewSize"></span>
                                </div>
                                <button type="button" class="file-preview-remove" id="filePreviewRemove" title="Remove file">
                                    <i class="ti ti-x"></i>
                                </button>
                            </div>
                            <div class="file-error sig-hidden" id="fileError">
                                <i class="ti ti-alert-circle"></i>
                                <span id="fileErrorText"></span>
                            </div>
                        </div>
                    </div>
                    <div class="show-action-btns">
                        <?php if ($nextAction): ?>
                            <button type="submit" name="action" value="approve"
                                formaction="<?= url('forms/' . $formId . '/approve/' . htmlspecialchars($nextAction)) ?>"
                                class="btn btn-success btn-block show-btn-approve">
                                <i class="ti ti-circle-check"></i>
                                <?= htmlspecialchars($approveLabel) ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($nextAction !== 'submit'): ?>
                        <button type="submit" name="action" value="reject"
                            formaction="<?= url('forms/' . $formId . '/reject') ?>"
                            class="btn btn-outline-danger btn-block" id="btn-reject" formnovalidate>
                            <i class="ti ti-x"></i> Reject
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($form['status'] === 'completed' && !in_array($form['form_type'], ['leave_application', 'overtime_authorization'], true) && (int)$roleId === 5 && !$hasPaymentProof): ?>
        <div class="card card-action show-action-card">
            <div class="card-header card-header--flex">
                <i class="ti ti-file-invoice"></i>
                Disbursement Confirmation
            </div>
            <div class="card-body">
                <form method="POST" action="<?= url('forms/' . $formId . '/payment-proof') ?>" enctype="multipart/form-data">
                    <?= \App\Helpers\Csrf::field(); ?>
                    <div class="form-group">
                        <label class="show-field-label" for="paymentProofFile">
                            Invoice / Receipt Attachments
                            <span class="show-field-hint">required</span>
                        </label>
                        <input type="file" name="payment_proof[]" id="paymentProofFile" accept="image/jpeg,image/png,image/gif,application/pdf" multiple required data-multi-attachment="paymentProofFileList">
                        <button type="button" class="btn btn-ghost btn-sm attachment-add-more" data-add-files-for="paymentProofFile" hidden>
                            <i class="ti ti-plus"></i> Add more files
                        </button>
                        <div class="selected-attachments" id="paymentProofFileList" aria-live="polite"></div>
                    </div>
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="ti ti-upload"></i> Upload Document
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($form['status'] === 'completed' && !in_array($form['form_type'], ['leave_application', 'overtime_authorization', 'reimbursement'], true) && $hasPaymentProof && $isOwner && !$hasLiquidationProof): ?>
        <div class="card card-action show-action-card">
            <div class="card-header card-header--flex">
                <i class="ti ti-file-description"></i>
                Expense Liquidation
            </div>
            <div class="card-body">
                <form method="POST" action="<?= url('forms/' . $formId . '/liquidation-proof') ?>" enctype="multipart/form-data">
                    <?= \App\Helpers\Csrf::field(); ?>
                    <div class="form-group">
                        <label class="show-field-label" for="liquidationProofFile">
                            Official Receipt / Invoices
                            <span class="show-field-hint">required</span>
                        </label>
                        <input type="file" name="liquidation_proof[]" id="liquidationProofFile" accept="image/jpeg,image/png,image/gif,application/pdf" multiple required data-multi-attachment="liquidationProofFileList">
                        <button type="button" class="btn btn-ghost btn-sm attachment-add-more" data-add-files-for="liquidationProofFile" hidden>
                            <i class="ti ti-plus"></i> Add more files
                        </button>
                        <div class="selected-attachments" id="liquidationProofFileList" aria-live="polite"></div>
                    </div>
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="ti ti-upload"></i> Submit Liquidation 
                    </button>
                </form>
            </div> 
        </div>
        <?php endif; ?> 

        <?php if (!$canAct && $form['status'] === 'rejected'): ?>
        <div class="card show-terminal-card">
            <div class="card-body">
                <div class="show-terminal show-terminal--danger">
                    <div class="show-terminal-icon show-terminal-icon--danger"><i class="ti ti-circle-x"></i></div>
                    <div>
                        <div class="show-terminal-title">Request Rejected</div>
                        <div class="show-terminal-sub">Check the approval trail above for the rejection reason.</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
 
    </div><!-- /.show-right-col -->

</div>

<script nonce="<?= htmlspecialchars($GLOBALS['csp_nonce'] ?? '') ?>">window.SHARE_FORM_ID = <?= (int) $formId ?>;</script>
<script src='<?= url('scripts/share-form.js') ?>'></script>
<script src='<?= url('scripts/show.js') ?>'></script>