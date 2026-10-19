<?php
    $error = $_SESSION['error'] ?? null;
    $status = $_SESSION['status'] ?? null;
    unset($_SESSION['error'], $_SESSION['status']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password — Automated Requesting System</title>
    <link href="<?= url('stylesheets/auth.css') ?>" rel="stylesheet">
</head>
<body>
    <div class="forgot-password-card">
        <div class="forgot-password-title">⚙ Reset Password</div>

        <p class="card-subtitle">Enter your email and we'll send you a reset link.</p>

        <?php if ($error): ?>
            <p class="form-error" role="alert"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if ($status): ?>
            <p class="form-success" role="alert"><?= htmlspecialchars($status) ?></p>
        <?php endif; ?>

        <form method="POST" action="<?= url('forgot-password') ?>">
            <?= \App\Helpers\Csrf::field() ?>
            <div class="card-form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="name@email.com" required autofocus>
            </div>
            <button type="submit" class="card-btn">Send Reset Link</button>
        </form>

        <div class="card-divider"></div>

        <div class="card-footer">
            <a href="<?= url('login') ?>" class="card-back-link">
                <i data-lucide="arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
    <script src="<?= url('scripts/lucide.min.js') ?>"></script>
    <script src="<?= url('scripts/auth.js') ?>"></script>
</body>
</html>