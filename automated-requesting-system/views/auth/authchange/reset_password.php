<?php
    $token  = $_GET['token'] ?? '';
    $error  = $_SESSION['error']  ?? null;
    $status = $_SESSION['status'] ?? null;
    unset($_SESSION['error'], $_SESSION['status']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create New Password — Automated Requesting System</title>
    <link href="<?= url('stylesheets/auth.css') ?>" rel="stylesheet">
</head>
<body>
    <div class="forgot-password-card">
        <div class="forgot-password-title">⚙ Create New Password</div>

        <p class="card-subtitle">Enter your new password below to secure your account.</p>

        <?php if ($error): ?>
            <p class="form-error" role="alert"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="<?= url('update-password') ?>">
            <?= \App\Helpers\Csrf::field() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="card-form-group">
                <label for="password">New Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password"
                           placeholder="••••••••" autocomplete="new-password" required autofocus>
                    <button type="button" class="toggle-icon" id="toggleBtn" aria-label="Toggle password visibility">
                        <i data-lucide="eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="card-form-group">
                <label for="password_confirmation">Confirm New Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           placeholder="••••••••" autocomplete="new-password" required>
                    <button type="button" class="toggle-icon" id="toggleBtnConfirm" aria-label="Toggle confirm password visibility">
                        <i data-lucide="eye" id="eyeIconConfirm"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="card-btn">Update Password</button>
        </form>

        <div class="card-divider"></div>

        <div class="card-footer">
            <a href="<?= url('login') ?>" class="card-back-link">
                <i data-lucide="x-circle" class="icon-sm"></i> Cancel and Login
            </a>
        </div>
    </div>
    <script src="<?= url('scripts/lucide.min.js') ?>"></script>
    <script src="<?= url('scripts/auth.js') ?>"></script>
</body>
</html>