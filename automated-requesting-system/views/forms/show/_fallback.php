<?php // Generic fallback: renders all saved fields as read-only inputs ?>
<div class="form-card">
    <div class="form-section-title">Form Data</div>
    <?php foreach ($data as $key => $value):
        if ($key === 'csrf_token') continue;
        $label = ucwords(str_replace('_', ' ', $key));
    ?>
    <?php if (is_array($value)): ?>
        <?php foreach ($value as $idx => $v): ?>
        <div class="form-group">
            <label><?= htmlspecialchars($label) ?> <?= $idx + 1 ?></label>
            <input type="text" value="<?= htmlspecialchars((string)$v) ?>" readonly>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="form-group">
            <label><?= htmlspecialchars($label) ?></label>
            <input type="text" value="<?= htmlspecialchars((string)$value) ?>" readonly>
        </div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="form-card">
    <div class="form-section-title">Submitted</div>
    <div class="form-group"><input type="text" value="<?= htmlspecialchars(date('M d, Y · g:i A', strtotime($form['created_at']))) ?>" readonly></div>
</div>
