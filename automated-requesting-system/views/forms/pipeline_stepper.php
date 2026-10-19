<?php
/**
 * pipeline_stepper.php
 *
 * Renders the horizontal approval-progress stepper on the form detail view.
 *
 * Expects: $form — the form row (requires 'form_type' and 'status')
 * Optionally: 
 * $approvalSteps — array of approval rows (id, sequence,
 *                  approver_id, status, approved_at, remarks, full_name)
 * When present, each completed node shows the approver name + date.
 *
 */

use App\Helpers\FormLabels;

// ── Choose the correct pipeline for this form type ── /
$financeTypes = ['advance_payment', 'request_for_payment', 'reimbursement', 'liquidation'];
$isFinance = in_array($form['form_type'], $financeTypes, true);

if (($form['form_type'] ?? '') === 'reimbursement') {
    $statusOrder = [
        'draft' => 'Draft (Preview)',
        'submitted' => 'Request Submitted',
        'immediatehead_approved' => 'Supervisor Verification',
        'hr_verified' => 'HR Verification',
        'process_approved' => 'Accounting Validation',
        'finance_reviewed' => 'Financial Assessment',
        'completed' => 'Final Authorization',
    ];
} elseif (($form['form_type'] ?? '') === 'vehicle_request') {
    $statusOrder = [
        'draft' => 'Draft (Preview)',
        'submitted' => 'Request Submitted',
        'immediatehead_approved' => 'Supervisor Verification',
        'department_reviewed' => 'Admin Evaluation',
        'completed' => 'Final Admin Approval',
    ];
} elseif ($isFinance) {
    $statusOrder = [
        'draft' => 'Draft (Preview)',
        'submitted' => 'Request Submitted',
        'immediatehead_approved' => 'Supervisor Verification',
        'process_approved' => 'Accounting Validation',
        'finance_reviewed' => 'Financial Assessment',
        'completed' => 'Final Authorization',
    ];
} else {
    // Admin pipeline: Draft (Preview) → Request Submitted → Supervisor Verification → Review (Department Head) → Final Authorization
    $statusOrder = [
        'draft' => 'Draft (Preview)',
        'submitted' => 'Request Submitted',
        'immediatehead_approved' => 'Supervisor Verification',
        'department_reviewed' => 'Evaluation (Dept. Head Checking)',
        'completed' => 'Final (Management Approval)',
    ];
}

$submitterDepartment = strtolower(trim((string) ($form['submitter_department'] ?? '')));
$specialAdminDepartment = preg_match(
    '/\b(management|human\s+resources|hr|admin(?:istration)?)\b/',
    $submitterDepartment
) === 1;
$useFinalHrTrail = in_array($form['form_type'] ?? '', ['leave_application', 'overtime_authorization'], true)
    && $specialAdminDepartment;
$useSupervisorFinalHrTrail = in_array($form['form_type'] ?? '', ['leave_application', 'overtime_authorization'], true)
    && preg_match('/\b(accounting|information\s+technology|it|finance|logistics)\b|(?<![a-z-])sales\b|\bpre-sales\s*\(engineer\)|\bpost-sales\s*\(engineer\)/i', $submitterDepartment) === 1;
$useHrRecordkeepingTrail = $useFinalHrTrail || $useSupervisorFinalHrTrail;
if ($useFinalHrTrail) {
    $statusOrder = [
        'submitted' => 'Request Submitted',
        'final_authorization' => 'Final Authorization',
        'hr_recordkeeping' => 'Human Resources Recordkeeping',
    ];
} elseif ($useSupervisorFinalHrTrail) {
    $statusOrder = [
        'submitted' => 'Request Submitted',
        'immediatehead_approved' => 'Supervisor Verification',
        'final_authorization' => 'Final Authorization',
        'hr_recordkeeping' => 'Human Resources Recordkeeping',
    ];
}

$formStatus = $form['status'] ?? 'draft';
$isRejected = $formStatus === 'rejected';
$statusKeys = array_keys($statusOrder);
$currentIndex = array_search($formStatus, $statusKeys, true);
if ($currentIndex === false) $currentIndex = 0;

// Build a quick lookup: sequence → array of approval rows. Usually one
// row per sequence, but stages with concurrent approvers (reimbursement's
// dual-checker sign-off) can have more than one.
$stepsBySeq = [];
if (!empty($approvalSteps) && is_array($approvalSteps)) {
    foreach ($approvalSteps as $as) {
        $stepsBySeq[(int)$as['sequence']][] = $as;
    }
}
$activeSequence = null;
foreach ($stepsBySeq as $sequence => $rows) {
    if (count(array_filter($rows, fn($row) => $row['status'] === 'pending')) > 0) {
        $activeSequence = (int) $sequence;
        break;
    }
}
$isManagementImmediateHeadReimbursement = ($form['form_type'] ?? '') === 'reimbursement'
    && (int) ($form['submitter_role_id'] ?? 0) === 2
    && preg_match('/\bmanagement\b/i', (string) ($form['submitter_department'] ?? '')) === 1;
$hasAssignedSupervisorApproval = !empty(array_filter(
    $stepsBySeq[2] ?? [],
    static fn(array $row): bool => (int) ($row['approver_id'] ?? 0) !== (int) ($form['submitted_by'] ?? 0)
));
// Pipeline index maps directly to sequence number:
// index 0 = draft (no approval row), index 1 = sequence 1 (submitter's row),
// index 2 = sequence 2 (first approver), etc.

// Normalises a stored file path and reports whether it's an image, for the
// inline attachment/signature preview.
$normalizeStepFile = function (?string $path): array {
    if (!$path) return ['path' => '', 'isImage' => false];
    if (str_starts_with($path, 'storage/approvals/')) {
        $path = 'uploads/approvals/' . basename($path);
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return ['path' => $path, 'isImage' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)];
};
$formData = json_decode((string) ($form['data'] ?? '[]'), true);
$formData = is_array($formData) ? $formData : [];
$automaticRemarks = [
    'Submitted',
    'Immediate Head Approval',
    'Review Approval',
    'Grant Approval Request',
    'Process Approval',
    'Evaluation Approval',
    'Accounting Validation',
    'Process (Accounting Checking)',
    '(Admin override)',
    '(AdminApprover stand-in)',
    '(FinalApprover — first to act)',
    '(HRVerifier — first to act)',
    '(FinanceHead — first to act)',
    '(Accounting — first to act)',
];
?>

<div class="approval-trail">
    <?php $displayStepNumber = 0; ?>
    <?php foreach ($statusOrder as $statusKey => $label):
        $i = array_search($statusKey, $statusKeys, true);
        $stepSequence = $useHrRecordkeepingTrail
            ? ($useSupervisorFinalHrTrail
                ? ['submitted' => 1, 'immediatehead_approved' => 2, 'final_authorization' => 3, 'hr_recordkeeping' => null][$statusKey]
                : ['submitted' => 1, 'final_authorization' => 2, 'hr_recordkeeping' => null][$statusKey])
            : $i;
        if ($statusKey === 'draft') continue;
        if (!$useHrRecordkeepingTrail
            && $statusKey === 'immediatehead_approved'
            && in_array((int) ($form['submitter_role_id'] ?? 0), [2, 5, 9], true)
            && !(
                in_array(($form['form_type'] ?? ''), ['advance_payment', 'request_for_payment', 'reimbursement'], true)
                && (int) ($form['submitter_role_id'] ?? 0) === 2
                && !empty($stepsBySeq[2])
                && !(
                    ($form['form_type'] ?? '') === 'reimbursement'
                    && $isManagementImmediateHeadReimbursement
                    && !$hasAssignedSupervisorApproval
                )
            )
        ) continue;
        if (!$useHrRecordkeepingTrail && $statusKey === 'immediatehead_approved' && empty($stepsBySeq[2])) continue;
        if (in_array(($form['form_type'] ?? ''), ['advance_payment', 'request_for_payment'], true)
            && (int) ($form['submitter_role_id'] ?? 0) === 5
            && $statusKey === 'process_approved'
        ) continue;
        if (($form['form_type'] ?? '') === 'reimbursement'
            && (int) ($form['submitter_role_id'] ?? 0) === 5
            && $statusKey === 'process_approved'
        ) continue;
        $displayStepNumber++;

        // Look up approval rows for this step (usually one, sometimes two
        // for a concurrent stage — either dual sign-off, where BOTH must
        // sign [e.g. Vehicle Request's checker-approval], or a race stage,
        // where EITHER qualified approver acts and the other is auto-skipped
        // [Final Approval, shared by the real Final Approver and AdminApprover]).
        $rowsAtStep = $stepSequence !== null ? ($stepsBySeq[$stepSequence] ?? []) : [];
        if ($useHrRecordkeepingTrail && $statusKey === 'final_authorization') {
            $rowsAtStep = array_values(array_filter(
                $rowsAtStep,
                static fn(array $row): bool => (int) ($row['approver_role_id'] ?? 0) === 6
            ));
        }
        $actedRows = array_values(array_filter(
            $rowsAtStep, fn($r) => in_array($r['status'], ['approved', 'rejected'], true)
        ));
        $pendingRows = array_values(array_filter($rowsAtStep, fn($r) => $r['status'] === 'pending'));
        $skippedRows = array_values(array_filter($rowsAtStep, fn($r) => $r['status'] === 'skipped'));
        $isSequentialReimbursementStage = $form['form_type'] === 'reimbursement'
            && in_array($i, [3, 4], true);
        $isDualStage = count($rowsAtStep) > 1 && !$isSequentialReimbursementStage;
        $isRaceStage = $isDualStage && count($skippedRows) > 0;
        $rejectedRows = array_values(array_filter($rowsAtStep, fn($r) => $r['status'] === 'rejected'));
        $isDone = $stepSequence > 0
            && !empty($rowsAtStep)
            && empty($pendingRows)
            && empty($rejectedRows)
            && count($actedRows) + count($skippedRows) === count($rowsAtStep);

        if ($useHrRecordkeepingTrail && $statusKey === 'hr_recordkeeping') {
            $state = $formStatus === 'completed' ? 'done' : 'pending';
        } elseif ($stepSequence === 1) {
            $state = $formStatus === 'draft' ? 'current' : 'done';
        } elseif ($isRejected) {
            $state = !empty($rejectedRows) ? 'rejected' : ($isDone ? 'done' : 'pending');
        } elseif ($isDone) {
            $state = 'done';
        } elseif ($activeSequence === $stepSequence) {
            $state = 'current';
        } else {
            $state = 'pending';
        }
    ?>
    <div class="approval-step <?= $state === 'done' ? 'is-done' : '' ?>
                               <?= $state === 'current' ? 'is-current'  : '' ?>
                               <?= ($isRejected && $state !== 'done') ? 'is-rejected' : '' ?>
                               <?= in_array($statusKey, ['completed', 'final_authorization'], true) ? 'approval-step--final-authorization' : '' ?>
                               <?= $statusKey === 'completed' && in_array($form['form_type'], ['advance_payment', 'request_for_payment', 'reimbursement'], true) ? 'approval-step--no-final-connector' : '' ?>
                               <?= $useHrRecordkeepingTrail && $statusKey === 'hr_recordkeeping' ? 'approval-step--hr-recordkeeping' : '' ?>">
        <div class="step-dot <?= $state ?>">
            <?= $state === 'done' ? '<i class="ti ti-check"></i>' : $displayStepNumber ?>
        </div>
        <div class="step-meta">
            <div class="step-name">
                <?= htmlspecialchars($label) ?>
                <?php if ($isRaceStage): ?>
                    <span class="step-dual-badge">Approved by 1 of <?= count($rowsAtStep) ?> qualified approvers</span>
                <?php elseif ($isDualStage): ?>
                    <span class="step-dual-badge"><?= count($actedRows) ?>/<?= count($rowsAtStep) ?> signed</span>
                <?php endif; ?>
            </div>

            <?php foreach ($actedRows as $row):
                $rowName = htmlspecialchars($row['full_name'] ?? '');
                $rowDate = !empty($row['approved_at']) ? date('M d, Y', strtotime($row['approved_at'])) : '';
            ?>
                <div class="step-signee<?= $isDualStage ? ' step-signee--dual' : '' ?>">
                    <?php if ($rowName): ?><div class="step-approver"><?= $rowName ?></div><?php endif; ?>
                    <?php if ($rowDate): ?><div class="step-date"><?= $rowDate ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($useHrRecordkeepingTrail && $statusKey === 'hr_recordkeeping'): ?>
                <div class="step-signee">
                    <div class="step-approver">HRVerifier</div>
                    <?php if ($formStatus === 'completed'): ?>
                        <div class="step-date"><?= !empty($form['updated_at']) ? htmlspecialchars(date('M d, Y', strtotime($form['updated_at']))) : '' ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php
                $stepDetails = [];
                foreach ($actedRows as $row) {
                    $rowRemarks = trim((string) ($row['remarks'] ?? ''));
                    if (in_array($rowRemarks, $automaticRemarks, true)) {
                        $rowRemarks = '';
                    }
                    $rowFile = $normalizeStepFile($row['file_path'] ?? null);
                    $stepDetails[] = [
                        'type' => 'row',
                        'remarks' => $rowRemarks,
                        'file' => $rowFile,
                        'fileKind' => $rowFile['isImage'] ? 'signature' : 'attachment',
                        'showStageLabel' => true,
                    ];
                }
                if ($stepSequence === 1 && !empty($formData['attachments']) && is_array($formData['attachments'])) {
                    foreach ($formData['attachments'] as $attachmentPath) {
                        if (is_string($attachmentPath) && $attachmentPath !== '') {
                            $stepDetails[] = [
                                'type' => 'row',
                                'remarks' => '',
                                'file' => $normalizeStepFile($attachmentPath),
                                'fileKind' => 'attachment',
                                'showStageLabel' => false,
                            ];
                        }
                    }
                }
                if ($i === 3
                    && in_array($form['form_type'], ['advance_payment', 'request_for_payment', 'reimbursement'], true)
                    && isset($form['approved_amount'])
                    && $form['approved_amount'] !== null
                    && $form['approved_amount'] !== '') {
                    $stepDetails[] = ['type' => 'amount', 'value' => 'Approved Amount: PHP ' . number_format((float)$form['approved_amount'], 2)];
                }
            ?>

            <?php if (!empty($stepDetails)): ?>
                <?php $stepDetailsModalId = 'approvalStepDetails' . $i; ?>
                <button
                    type="button"
                    class="step-details-trigger"
                    data-modal="<?= $stepDetailsModalId ?>"
                    aria-haspopup="dialog"
                >View Details</button>
                <div class="ars-modal-backdrop step-details-modal-backdrop" id="<?= $stepDetailsModalId ?>" role="dialog" aria-modal="true" aria-labelledby="<?= $stepDetailsModalId ?>Title" hidden>
                    <div class="ars-modal step-details-modal">
                        <div class="step-details-modal-header">
                            <h3 class="ars-modal-title" id="<?= $stepDetailsModalId ?>Title"><?= htmlspecialchars($label) ?> Details</h3>
                            <button type="button" class="step-details-modal-close" data-modal-close="<?= $stepDetailsModalId ?>" aria-label="Close details">
                                <i class="ti ti-x"></i>
                            </button>
                        </div>
                        <div class="step-details-body">
                        <?php foreach ($stepDetails as $detail): ?>
                            <?php if (($detail['type'] ?? '') === 'amount'): ?>
                                <div class="step-remark">
                                    <strong>Approved Amount:</strong>
                                    <span><?= htmlspecialchars($detail['value']) ?></span>
                                </div>
                            <?php else: ?>
                                <?php if (!empty($detail['remarks']) || !empty($detail['showStageLabel'])): ?>
                                    <div class="step-remark">
                                        <strong>Remarks:</strong>
                                        <span><?= htmlspecialchars($detail['remarks'] !== '' ? $detail['remarks'] : $label) ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($detail['file']['path'])): ?>
                                    <div class="step-remark">
                                        <strong>Attachment:</strong>
                                        <a href="<?= url($detail['file']['path']) ?>"
                                           class="step-attachment"
                                           data-approval-preview
                                           data-preview-type="<?= $detail['file']['isImage'] ? 'image' : 'document' ?>"
                                           data-preview-title="<?= ($detail['fileKind'] ?? '') === 'signature' ? 'Signature' : 'Attachments' ?>">
                                            <?php if ($detail['file']['isImage']): ?>
                                                <img src="<?= url($detail['file']['path']) ?>" alt="" class="step-attachment-thumb">
                                                <span><?= ($detail['fileKind'] ?? '') === 'signature' ? 'View Signature' : 'View Attachments' ?></span>
                                            <?php else: ?>
                                                <span class="step-attachment-icon"><i class="ti ti-file-type-pdf"></i></span>
                                                <span>View Attachments</span>
                                            <?php endif; ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($skippedRows as $row): $rowName = htmlspecialchars($row['full_name'] ?? ''); ?>
                <div class="step-signee step-signee--skipped">
                    <?php if ($rowName): ?><div class="step-approver step-approver--muted"><?= $rowName ?></div><?php endif; ?>
                    <div class="step-remark step-remark--muted">
                        <i class="ti ti-arrow-guide"></i>
                        Not needed — already approved by another qualified approver
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($isDualStage && $pendingRows): ?>
                <div class="step-waiting">
                    <i class="ti ti-hourglass-low"></i>
                    Waiting for Approver 
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

<?php if ($form['status'] === 'completed'): ?>
    <?php
        $proofData = json_decode((string) ($form['data'] ?? '[]'), true);
        $proofData = is_array($proofData) ? $proofData : [];
        $paymentProofAttachments = $proofData['payment_proof_attachments'] ?? [];
        $paymentProofAttachments = is_array($paymentProofAttachments) ? $paymentProofAttachments : [];
        $liquidationProofAttachments = $proofData['liquidation_proof_attachments'] ?? [];
        $liquidationProofAttachments = is_array($liquidationProofAttachments) ? $liquidationProofAttachments : [];
        $paymentProofPath = $proofData['payment_proof_path'] ?? ($paymentProofAttachments[0]['path'] ?? null);
        $liquidationProofPath = $proofData['liquidation_proof_path'] ?? ($liquidationProofAttachments[0]['path'] ?? null);
        if (!$paymentProofAttachments && $paymentProofPath) {
            $paymentProofAttachments = [['path' => $paymentProofPath, 'name' => basename($paymentProofPath)]];
        }
        if (!$liquidationProofAttachments && $liquidationProofPath) {
            $liquidationProofAttachments = [['path' => $liquidationProofPath, 'name' => basename($liquidationProofPath)]];
        }
        $acquisitionCheckerName = null;
        foreach (($approvalSteps ?? []) as $approvalStep) {
            if ((int) ($approvalStep['approver_role_id'] ?? 0) === 5
                && !empty($approvalStep['full_name'])) {
                $acquisitionCheckerName = $approvalStep['full_name'];
                break;
            }
        }
        $paymentProofName = $proofData['payment_proof_uploaded_by']
            ?? $acquisitionCheckerName
            ?? 'Assigned Acquisition Checker';
        $paymentProofDate = $proofData['payment_proof_uploaded_at'] ?? null;
        if (!$paymentProofDate && $paymentProofPath) {
            $paymentProofFile = __DIR__ . '/../../public/' . ltrim($paymentProofPath, '/');
            if (is_file($paymentProofFile)) {
                $paymentProofDate = date('Y-m-d H:i:s', filemtime($paymentProofFile));
            }
        }
        $liquidationProofName = $proofData['liquidation_proof_uploaded_by']
            ?? ($form['submitter_name'] ?? 'Requestor');
        $liquidationProofDate = $proofData['liquidation_proof_uploaded_at'] ?? null;
        if (!$liquidationProofDate && $liquidationProofPath) {
            $liquidationProofFile = __DIR__ . '/../../public/' . ltrim($liquidationProofPath, '/');
            if (is_file($liquidationProofFile)) {
                $liquidationProofDate = date('Y-m-d H:i:s', filemtime($liquidationProofFile));
            }
        }
    ?>

    <div class="approval-completed-note" role="status">
        <div class="approval-completed-icon"><i class="ti ti-circle-check"></i></div>
        <div>
            <div class="approval-completed-title"><?= in_array($form['form_type'], ['leave_application', 'overtime_authorization'], true) ? 'Successfully Processed' : 'Request Completed' ?></div>
            <div class="approval-completed-sub"><?= in_array($form['form_type'], ['leave_application', 'overtime_authorization'], true) ? 'Records updated successfully.' : 'Fully approved and completed.' ?></div>
        </div>
    </div>

    <?php if (!in_array($form['form_type'], ['leave_application', 'overtime_authorization'], true)): ?>
    <div class="approval-trail approval-trail--post-authorization">
        <div class="approval-step <?= $paymentProofPath ? 'is-done' : 'is-current' ?>">
            <div class="step-dot <?= $paymentProofPath ? 'done' : 'current' ?>">
                <?= $paymentProofPath ? '<i class="ti ti-check"></i>' : $displayStepNumber + 1 ?>
            </div>
            <div class="step-meta">
                <div class="step-name">Disbursement Confirmation</div>
                <div class="step-signee">
                    <div class="step-approver"><?= htmlspecialchars($paymentProofName) ?></div>
                    <?php if ($paymentProofDate): ?>
                        <div class="step-date"><?= htmlspecialchars(date('M d, Y', strtotime($paymentProofDate))) ?></div>
                    <?php endif; ?>
                </div>

                <?php $paymentDetailsModalId = 'paymentProofDetails'; ?>
                <button
                    type="button"
                    class="step-details-trigger"
                    data-modal="<?= $paymentDetailsModalId ?>"
                    aria-haspopup="dialog"
                >View Details</button>
                <div class="ars-modal-backdrop step-details-modal-backdrop" id="<?= $paymentDetailsModalId ?>" role="dialog" aria-modal="true" aria-labelledby="<?= $paymentDetailsModalId ?>Title" hidden>
                    <div class="ars-modal step-details-modal">
                        <div class="step-details-modal-header">
                            <h3 class="ars-modal-title" id="<?= $paymentDetailsModalId ?>Title">Disbursement Confirmation Details</h3>
                            <button type="button" class="step-details-modal-close" data-modal-close="<?= $paymentDetailsModalId ?>" aria-label="Close details">
                                <i class="ti ti-x"></i>
                            </button>
                        </div>
                        <div class="step-details-body">
                        <?php if ($paymentProofAttachments): ?>
                            <div class="step-remark">
                                <strong>Attachment:</strong>
                                <?php foreach ($paymentProofAttachments as $attachment): ?>
                                    <?php if (!empty($attachment['path'])): ?>
                                        <a
                                            href="<?= url($attachment['path']) ?>"
                                            class="step-attachment"
                                            data-approval-preview
                                            data-preview-title="Attachments"
                                            data-preview-type="<?= in_array(strtolower(pathinfo($attachment['path'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? 'image' : 'document' ?>"
                                        >
                                            <span><?= htmlspecialchars($attachment['name'] ?? basename($attachment['path'])) ?></span>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="step-remark"> 
                                <strong>Status:</strong>
                                <span>Awaiting Payment Document</span>
                            </div> 
                            <?php endif; ?>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!in_array($form['form_type'], ['leave_application', 'overtime_authorization', 'reimbursement'], true)): ?>
        <div class="approval-step <?= $liquidationProofPath ? 'is-done' : ($paymentProofPath ? 'is-current' : '') ?>">
            <div class="step-dot <?= $liquidationProofPath ? 'done' : ($paymentProofPath ? 'current' : 'pending') ?>">
                <?= $liquidationProofPath ? '<i class="ti ti-check"></i>' : $displayStepNumber + 2 ?>
            </div>
            <div class="step-meta">
                <div class="step-name">Expense Liquidation</div>
                <div class="step-signee">
                    <div class="step-approver"><?= htmlspecialchars($liquidationProofName) ?></div>
                    <?php if ($liquidationProofDate): ?>
                        <div class="step-date"><?= htmlspecialchars(date('M d, Y', strtotime($liquidationProofDate))) ?></div>
                    <?php endif; ?>
                </div>
                <?php $liquidationDetailsModalId = 'liquidationProofDetails'; ?>
                <button
                    type="button"
                    class="step-details-trigger"
                    data-modal="<?= $liquidationDetailsModalId ?>"
                    aria-haspopup="dialog"
                >View Details</button>
                <div class="ars-modal-backdrop step-details-modal-backdrop" id="<?= $liquidationDetailsModalId ?>" role="dialog" aria-modal="true" aria-labelledby="<?= $liquidationDetailsModalId ?>Title" hidden>
                    <div class="ars-modal step-details-modal">
                        <div class="step-details-modal-header">
                            <h3 class="ars-modal-title" id="<?= $liquidationDetailsModalId ?>Title">Expense Liquidation Details</h3>
                            <button type="button" class="step-details-modal-close" data-modal-close="<?= $liquidationDetailsModalId ?>" aria-label="Close details">
                                <i class="ti ti-x"></i>
                            </button>
                        </div>
                        <div class="step-details-body">
                        <?php if ($liquidationProofAttachments): ?>
                            <div class="step-remark">
                                <strong>OR, AR, or Invoice:</strong>
                                <?php foreach ($liquidationProofAttachments as $attachment): ?>
                                    <?php if (!empty($attachment['path'])): ?>
                                        <a
                                            href="<?= url($attachment['path']) ?>"
                                            class="step-attachment"
                                            data-approval-preview
                                            data-preview-title="Attachments"
                                            data-preview-type="<?= in_array(strtolower(pathinfo($attachment['path'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? 'image' : 'document' ?>"
                                        >
                                            <span><?= htmlspecialchars($attachment['name'] ?? basename($attachment['path'])) ?></span>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="step-remark">
                                <strong>Status:</strong>
                                <span>Pending Liquidation</span> 
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

</div>

<div class="ars-modal-backdrop approval-attachment-modal-backdrop" id="approvalAttachmentPreview" role="dialog" aria-modal="true" aria-labelledby="approvalAttachmentPreviewTitle" hidden>
    <div class="ars-modal approval-attachment-modal">
        <div class="step-details-modal-header">
            <h3 class="ars-modal-title" id="approvalAttachmentPreviewTitle">Attachment Preview</h3>
            <button type="button" class="step-details-modal-close" data-modal-close="approvalAttachmentPreview" aria-label="Close attachment preview">
                <i class="ti ti-x"></i>
            </button>
        </div>
        <div class="approval-attachment-preview-content">
            <img class="approval-attachment-preview-image" id="approvalAttachmentPreviewImage" alt="" hidden>
            <iframe class="approval-attachment-preview-document" id="approvalAttachmentPreviewDocument" title="Attachment preview" hidden></iframe>
        </div>
    </div>
</div>

<?php if ($isRejected): ?>
    <p class="stepper-rejected-note">
        <i class="ti ti-circle-x"></i> This form was <strong>rejected</strong>.
    </p>
<?php endif; ?>