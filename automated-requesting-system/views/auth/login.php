<?php
    $loginError = $_SESSION['login_error'] ?? null;
    $oldEmail = $_SESSION['old_email'] ?? '';
    $success = $_SESSION['success'] ?? null;
    unset($_SESSION['login_error'], $_SESSION['old_email'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — ARS</title>

    <link rel="manifest" href="<?= url('manifest.json') ?>">
    <meta name="theme-color" content="#0ea5e9">
    <link rel="icon" type="image/x-icon" href="<?= url('favicon.ico') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= url('images/icons/icon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= url('images/icons/icon-16.png') ?>">
    <link rel="apple-touch-icon" href="<?= url('images/icons/icon-180.png') ?>">
    <script src="<?= url('scripts/pwa-register.js') ?>"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="<?= url('stylesheets/auth.css') ?>" rel="stylesheet">
</head>

<body>

<div class="auth-wrap">

    <!-- Left branding panel -->
    <div class="auth-panel">
        <div class="auth-brand-icon">⚡</div>
        <div class="auth-brand-name">ARS</div>
        <div class="auth-brand-tag">Automated Requesting System</div>
        <p class="auth-panel-desc">Paperless forms, multi-level approvals, and real-time tracking — all in one place.</p>
        <ul class="auth-features">
            <li>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Advance Payment & Reimbursement
            </li>
            <li>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Leave & Overtime Authorization
            </li>
            <li>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Multi-level Approval Workflow
            </li>
        </ul>
        <p class="dev-sys">&copy; 2026 &middot; <span class="dev-name">Jet-Ly</span></p>
    </div>

    <!-- Right form panel -->
    <div class="auth-form-panel">

        <!-- Tab switcher -Removed 9/25/26
        <div class="auth-tabs" role="tablist">
            <button class="auth-tab <?= !$showSignup ? 'active' : '' ?>" data-tab="signin" role="tab">Sign In</button>
        </div>
        --> 

        <!-- Sign In pane -->
        <div class="auth-pane active" id="pane-signin">
            <div class="auth-title">Welcome back</div>
            <div class="auth-subtitle">Sign in to your ARS account</div>

            <?php if ($success): ?>
                <p class="form-success" role="alert"><?= htmlspecialchars($success) ?></p>
            <?php endif; ?>
            <?php if ($loginError): ?>
                <p class="form-error" role="alert"><?= htmlspecialchars($loginError) ?></p>
            <?php endif; ?>

            <form method="POST" action="<?= url('login') ?>">
                <?= \App\Helpers\Csrf::field() ?>
                <div class="auth-field">
                    <label for="login_email">Email or Username</label>
                    <input 
                        type="text" id="login_email" name="email"
                        value="<?= htmlspecialchars($oldEmail) ?>"
                        required autofocus autocomplete="username"
                        placeholder="Enter Email or Username"
                    >
                </div>
                <div class="auth-field">
                    <label for="login_password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="login_password" name="password"
                               required autocomplete="current-password" placeholder="••••••••">
                        <button type="button" class="toggle-icon" id="toggleBtn" aria-label="Toggle password visibility">
                            <i data-lucide="eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="auth-forgot">
                    <a href="<?= url('forgot-password') ?>">Forgot password?</a>
                </div>  
                <button type="submit" class="auth-btn">Sign In</button>
            </form>
        </div>

    </div>
</div>

<script src="<?= url('scripts/lucide.min.js') ?>"></script>
<script src="<?= url('scripts/auth.js') ?>"></script>
</body>
</html>