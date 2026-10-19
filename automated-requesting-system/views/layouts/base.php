<?php
    if (!defined('BASE_LOADED')) die('Direct access not allowed');
    
    $uri = $uri ?? '/';
    $roleLabels = [ 1 => 'Sys Admin', 2 => 'Immediate Head', 3 => 'Staff', 4 => 'Dept. Head', 5 => 'Acquisition Checker', 6 => 'Final Approver', 7 => 'Admin Approver', 8 => 'Finance Head', 9 => 'HR Verifier' ];
    $roleName = $roleLabels[$_SESSION['role_id']] ?? 'User';
    $initials = strtoupper(substr($_SESSION['user_name'], 0, 2));
    $sidebarAvatarUrl = avatar_url($_SESSION['avatar'] ?? null);
    $roleId = (int) $_SESSION['role_id'];
    $userId = (int) $_SESSION['user_id'];

    // ── Pending count — computed once per request, cached in session for 60s ─
    $pendingCount = 0;
    if ($roleId !== 3) {
        $cacheKey = "pending_count_{$userId}";
        $cacheTs = "pending_count_ts_{$userId}";
        $ttl = 60;

        if (
            isset($_SESSION[$cacheKey], $_SESSION[$cacheTs]) &&
            (time() - $_SESSION[$cacheTs]) < $ttl
        ) {
            $pendingCount = $_SESSION[$cacheKey];
        } else {
            try {
                $pendingCount = \App\Services\PendingCountService::forUser($userId, $roleId);
                $_SESSION[$cacheKey] = $pendingCount;
                $_SESSION[$cacheTs] = time();
            } catch (\Throwable $e) {}
        }
    }

    // ── Notifications ── //
    $notifItems = [];
    $notifUnread = 0;
    if ($userId) {
        try {
            $ns = db()->prepare(
                'SELECT id, form_id, type, message, link, is_read, created_at
                 FROM notifications
                 WHERE user_id = ?
                 ORDER BY created_at DESC LIMIT 10'
            );
            $ns->execute([$userId]);
            $notifItems = $ns->fetchAll(PDO::FETCH_ASSOC);
            $notifUnread = array_reduce($notifItems, fn($c, $r) => $c + (int)!$r['is_read'], 0);
        } catch (\Throwable $e) {}
    }
    $typeIcon = [
        'success' => ['dot' => '', 'color' => 'var(--success)'],
        'warning' => ['dot' => 'notif-dot-warning', 'color' => 'var(--warning)'],
        'danger' => ['dot' => 'notif-dot-danger', 'color' => 'var(--danger)'],
        'info' => ['dot' => '', 'color' => 'var(--primary)'],
    ];

    $statusLabels = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'immediatehead_approved' => 'With Immediate Head',
        'process_approved' => 'Processing',
        'department_reviewed' => 'Dept. Review',
        'finance_reviewed' => 'Finance Review',
        'final_approved' => 'Final Approved',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    // ── Theme settings — read per-user from user_settings, fall back to global ──
    $themeSettings = [];
    try {
        $themeUserId = (int)($_SESSION['user_id'] ?? 0);
        if ($themeUserId > 0) {
            // Try user-specific preferences first
            $tq = db()->prepare(
                "SELECT `key`, `value` FROM user_settings
                  WHERE user_id = ? AND `key` IN ( 'theme_color', 'theme_mode', 'sidebar_collapsed' )"
            );
            $tq->execute([$themeUserId]);
        } else {
            // Fallback: read from global settings
            $tq = db()->query(
                "SELECT `key`, `value` FROM settings
                  WHERE `key` IN ( 'theme_color', 'theme_mode', 'sidebar_collapsed' )"
            );
        }
        foreach ($tq->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $themeSettings[$row['key']] = $row['value'];
        }
    } catch (\Throwable $e) { /* user_settings table not yet migrated — use defaults */ }

    $themeColor = $themeSettings['theme_color'] ?? 'blue';
    $themeMode = $themeSettings['theme_mode'] ?? 'light';
    $sidebarCollapsed = ($themeSettings['sidebar_collapsed'] ?? '0') === '1';

    // Accent palette map
    $palettes = [
        'blue' => '--accent:#2563eb;--accent-glow:#2563eb20;--accent-alt:#3b82f6',
        'purple' => '--accent:#7c3aed;--accent-glow:#7c3aed20;--accent-alt:#8b5cf6',
        'green' => '--accent:#059669;--accent-glow:#05966920;--accent-alt:#10b981',
        'orange' => '--accent:#ea580c;--accent-glow:#ea580c20;--accent-alt:#f97316',
    ];
    $accentVars = $palettes[$themeColor] ?? $palettes['blue'];
    $GLOBALS['sidebar_collapsed'] = $sidebarCollapsed; // read by sidebar.php
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $themeMode === 'dark' ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(($pageTitle ?? 'Dashboard') . ' · ARS') ?></title>

    <!-- PWA: lets the app be "installed" from the browser and pinned to the
         Windows taskbar / Start Menu (or Dock/Home Screen on other OSes)
         with its own icon and standalone window, instead of a browser tab. -->
    <link rel="manifest" href="<?= url('manifest.json') ?>">
    <meta name="theme-color" content="#0ea5e9">
    <link rel="icon" type="image/x-icon" href="<?= url('favicon.ico') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= url('images/icons/icon-32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= url('images/icons/icon-16.png') ?>">
    <link rel="apple-touch-icon" href="<?= url('images/icons/icon-180.png') ?>">
    <script src="<?= url('scripts/pwa-register.js') ?>"></script>

    <link rel="stylesheet" href="<?= url('stylesheets/app.css') ?>">
    <link rel="stylesheet" href="<?= url('stylesheets/chat.css') ?>">
    <style nonce="<?= htmlspecialchars($GLOBALS['csp_nonce'] ?? '') ?>">:root{<?= $accentVars ?>}</style>
    <!-- CSRF token for fetch requests -->
    <meta name="csrf-token" content="<?= \App\Helpers\Csrf::token() ?>">
    <script nonce="<?= htmlspecialchars($GLOBALS['csp_nonce'] ?? '') ?>">window.__ARS_CSP_NONCE = '<?= htmlspecialchars($GLOBALS['csp_nonce'] ?? '') ?>';</script>
    <script src="<?= url('scripts/csp-utils.js') ?>"></script>
</head>
<body>
<div class="layout">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- ── NOTIFICATION PANEL ── -->
    <div class="notif-panel" id="notifPanel">
        <div class="notif-panel-header">
            <span class="notif-panel-title">
                Notifications
                <?php if ($notifUnread > 0): ?>
                    <span class="badge badge-danger notif-count-badge"><?= $notifUnread ?></span>
                <?php endif; ?>
            </span>
            <span class="notif-mark-read" id="markAllReadBtn">Mark all read</span>
        </div>

        <div id="notifList">
        <?php if (empty($notifItems)): ?>
            <div class="notif-empty">
                <i class="ti ti-bell-off empty-state-icon"></i>
                No notifications yet.
            </div>
        <?php else: ?>
            <?php foreach ($notifItems as $n):
                $tc = $typeIcon[$n['type']] ?? $typeIcon['info'];
                $ago = (new DateTime())->diff(new DateTime($n['created_at']));
                $agoStr  = $ago->days > 0 ? $ago->days . 'd ago'
                         : ($ago->h > 0 ? $ago->h . 'h ago'
                         : ($ago->i > 0 ? $ago->i . 'm ago' : 'Just now'));
                $isUnread = !(bool)$n['is_read'];
            ?>
            <div class="notif-item <?= $isUnread ? 'notif-item--unread' : '' ?>"
                 data-notif-id="<?= $n['id'] ?>"
                 <?= $n['link'] ? 'data-href="' . htmlspecialchars($n['link']) . '"' : '' ?>>
                <div class="notif-dot-sm <?= $tc['dot'] ?>"></div>
                <div>
                    <div class="notif-text"><?= htmlspecialchars($n['message']) ?></div>
                    <div class="notif-ago"><?= $agoStr ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>

    <!-- ── MAIN ── -->
    <div class="layout-right">

        <div id="topbar">
            <div class="topbar-left">
                <button type="button" class="mobile-menu-btn" id="mobileMenuBtn" title="Open menu" aria-label="Open menu">
                    <i class="ti ti-menu-2"></i>
                </button>
                <?php if (!empty($breadcrumbs)): ?>
                    <nav class="topbar-breadcrumb" aria-label="Breadcrumb">
                        <?php foreach ($breadcrumbs as $idx => $crumb): ?>
                            <?php if ($idx > 0): ?><span class="bc-sep"><i class="ti ti-chevron-right"></i></span><?php endif; ?>
                            <?php if (!empty($crumb['url'])): ?>
                                <a href="<?= htmlspecialchars($crumb['url']) ?>" class="bc-link"><?= htmlspecialchars($crumb['label']) ?></a>
                            <?php else: ?>
                                <span class="bc-current"><?= htmlspecialchars($crumb['label']) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>
                <?php else: ?>
                    <span class="topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></span>
                <?php endif; ?>
                <span class="topbar-date"><?= date('l, F j, Y') ?></span>
            </div>

            <div class="topbar-right">
                <a href="<?= url('chat') ?>" class="icon-btn chat-icon-wrap" title="Messages" id="chatBtn">
                    <i class="ti ti-message-circle"></i>
                    <span id="chatDot"></span>
                </a>

                <button class="icon-btn" id="notifBtn" title="Notifications">
                    <i class="ti ti-bell"></i>
                    <span class="notif-dot <?= $notifUnread === 0 ? 'notif-dot--hidden' : '' ?>" id="notifDot"></span>
                </button>

                <div class="new-req-wrap" id="newReqWrap">
                    <button type="button" class="btn-new-req" id="newReqBtn" aria-haspopup="true" aria-expanded="false">
                        <i class="ti ti-plus"></i> <span>New Request</span> <i class="ti ti-chevron-down"></i>
                    </button>
                    <div class="new-req-dropdown dept-hidden" id="newReqDropdown" role="menu">
                        <a href="<?= url('forms/advance-payment/create') ?>" role="menuitem">
                            <i class="ti ti-cash"></i> Advance Payment
                        </a>
                        <a href="<?= url('forms/reimbursement/create') ?>" role="menuitem">
                            <i class="ti ti-receipt"></i> Reimbursement
                        </a>
                        <a href="<?= url('forms/request-for-payment/create') ?>" role="menuitem">
                            <i class="ti ti-file-invoice"></i> Request for Payment
                        </a>
                        <a href="<?= url('forms/leave-application/create') ?>" role="menuitem">
                            <i class="ti ti-calendar-off"></i> Leave Application
                        </a>
                        <a href="<?= url('forms/overtime-authorization/create') ?>" role="menuitem">
                            <i class="ti ti-clock-plus"></i> Overtime Authorization
                        </a>
                        <a href="<?= url('forms/vehicle-request/create') ?>" role="menuitem">
                            <i class="ti ti-car"></i> Vehicle Request
                        </a>
                    </div>
                </div>

                <form method="POST" action="<?= url('logout') ?>" id="logoutForm" class="logout-form">
                    <?= \App\Helpers\Csrf::field() ?>
                    <button class="icon-btn" title="Logout">
                        <i class="ti ti-logout"></i>
                    </button>
                </form>
            </div>
        </div>

        <div id="main"><?= $content ?></div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script nonce="<?= htmlspecialchars($GLOBALS['csp_nonce'] ?? '') ?>">window.ARS_BASE = '<?= rtrim(parse_url($_ENV['APP_URL'] ?? '', PHP_URL_PATH) ?? '', '/') ?>'; window.APP_URL = window.ARS_BASE;</script>
<script src="<?= url('scripts/lucide.min.js') ?>"></script>
<script src="<?= url('scripts/form_table.js') ?>"></script>
<script src="<?= url('scripts/table-filter.js') ?>"></script>
<script src="<?= url('scripts/app.js') ?>"></script>

<?php if (isset($pageTitle) && $pageTitle === 'Messaging'): ?>
<script src="<?= url('scripts/chat.js') ?>"></script>
<?php endif; ?>

</body>
</html>