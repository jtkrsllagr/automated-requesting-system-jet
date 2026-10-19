<?php
/**
 * Error view partial
 * Used by FormController and ApprovalController for 403/404 responses.
 * Expects: $errorCode (int), $errorTitle (string), $errorMessage (string)
 */
?>
<div class="error-page">
    <i class="ti <?= $errorCode === 404 ? 'ti-file-off' : 'ti-lock' ?> error-icon"></i>
    <div>
        <div class="error-code"><?= $errorCode ?></div>
        <div class="error-title"><?= htmlspecialchars($errorTitle) ?></div>
        <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
    </div>
    <button type="button" class="btn btn-ghost btn-sm js-go-back">← Go Back</button>
</div>