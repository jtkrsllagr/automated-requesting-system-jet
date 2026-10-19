<?php 
    if (!defined('BASE_LOADED')) die('Direct access not allowed'); 
?>

<nav id="sidebar" class="<?= ($GLOBALS['sidebar_collapsed'] ?? false) ? 'collapsed' : '' ?>">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="ti ti-bolt"></i></div>
        <div class="brand-text">
            <div class="brand-name">ARS</div>
            <div class="brand-tag">Automated Requesting System</div>
        </div>
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle sidebar">
            <i class="ti ti-layout-sidebar-left-collapse" id="sidebarToggleIcon"></i>
        </button>
    </div>

    <div class="sidebar-nav">

        <a href="<?= url('dashboard') ?>"
           data-tooltip="Dashboard"
           class="<?= ($uri === '/dashboard' || $uri === '/') ? 'active' : '' ?>">
            <i class="ti ti-layout-dashboard"></i> <span>Dashboard</span>
        </a>

        <a href="<?= url('chat') ?>"
           data-tooltip="Messaging"
           class="<?= str_starts_with($uri, '/chat') ? 'active' : '' ?>">
            <i class="ti ti-message-circle"></i>
            <span>Messaging</span>
            <span class="badge-count" id="chatSidebarBadge"></span>
        </a>
 
        <div class="sidebar-group" data-group="my-submissions">
            <div class="sidebar-label sidebar-label--toggle" data-toggle="my-submissions">
                <i class="ti ti-send"></i>
                <span>My Submissions</span>
                <i class="ti ti-chevron-down sidebar-chevron"></i>
            </div>
            <div class="sidebar-group-links" id="group-my-submissions">
                <a href="<?= url('my-submissions?status=in_approval') ?>"
                   data-tooltip="In Approval"
                   class="<?= $uri === '/my-submissions' && ($_GET['status'] ?? 'in_approval') === 'in_approval' ? 'active' : '' ?>">
                    <i class="ti ti-hourglass"></i> <span>In Approval</span>
                </a> 
                <a href="<?= url('my-submissions?status=approved') ?>"
                   data-tooltip="Approved"
                   class="<?= $uri === '/my-submissions' && ($_GET['status'] ?? '') === 'approved' ? 'active' : '' ?>">
                    <i class="ti ti-circle-check"></i> <span>Approved</span>
                </a>
                <a href="<?= url('my-submissions?status=draft') ?>"
                   data-tooltip="Drafts"
                   class="<?= $uri === '/my-submissions' && ($_GET['status'] ?? '') === 'draft' ? 'active' : '' ?>">
                    <i class="ti ti-file-pencil"></i> <span>Drafts</span>
                </a>
                <a href="<?= url('my-submissions?status=rejected') ?>"
                   data-tooltip="Rejected"
                   class="<?= $uri === '/my-submissions' && ($_GET['status'] ?? '') === 'rejected' ? 'active' : '' ?>">
                    <i class="ti ti-circle-x"></i> <span>Rejected</span>
                </a>
            </div>
        </div>



        <div class="sidebar-group" data-group="finance">
            <div class="sidebar-label sidebar-label--toggle" data-toggle="finance">
                <i class="ti ti-coin"></i>
                <span>Finance</span>
                <i class="ti ti-chevron-down sidebar-chevron"></i>
            </div>
            <div class="sidebar-group-links" id="group-finance">
                <a href="<?= url('forms/advance-payment') ?>"
                   data-tooltip="Advance Payment"
                   class="<?= str_contains($uri, 'advance-payment') ? 'active' : '' ?>">
                    <i class="ti ti-cash"></i> <span>Advance Payment</span>
                </a>
                <a href="<?= url('forms/request-for-payment') ?>"
                   data-tooltip="Request for Payment"
                   class="<?= str_contains($uri, 'request-for-payment') ? 'active' : '' ?>">
                    <i class="ti ti-receipt"></i> <span>Request for Payment</span>
                </a>
                <a href="<?= url('forms/reimbursement') ?>"
                   data-tooltip="Reimbursement"
                   class="<?= str_contains($uri, 'reimbursement') ? 'active' : '' ?>">
                    <i class="ti ti-credit-card-refund"></i> <span>Reimbursement</span>
                </a>
            </div>
        </div>

        <div class="sidebar-group" data-group="admin">
            <div class="sidebar-label sidebar-label--toggle" data-toggle="admin">
                <i class="ti ti-building"></i>
                <span>Admin / HR</span>
                <i class="ti ti-chevron-down sidebar-chevron"></i> 
            </div>
            <div class="sidebar-group-links" id="group-admin">
                <a href="<?= url('forms/leave-application') ?>"
                   data-tooltip="Leave Application"
                   class="<?= str_contains($uri, 'leave-application') ? 'active' : '' ?>">
                    <i class="ti ti-beach"></i> <span>Leave Application</span>
                </a>
                <a href="<?= url('forms/overtime-authorization') ?>"
                   data-tooltip="Overtime Auth."
                   class="<?= str_contains($uri, 'overtime-authorization') ? 'active' : '' ?>">
                    <i class="ti ti-clock-hour-4"></i> <span>Overtime Auth.</span>
                </a>
                <a href="<?= url('forms/vehicle-request') ?>"
                   data-tooltip="Vehicle Request"
                   class="<?= str_contains($uri, 'vehicle-request') ? 'active' : '' ?>">
                    <i class="ti ti-car"></i> <span>Vehicle Request</span>
                </a>
            </div> 
        </div>

        <div class="sidebar-group" data-group="records">
            <div class="sidebar-label sidebar-label--toggle" data-toggle="records">
                <i class="ti ti-archive"></i>
                <span>Records</span>
                <i class="ti ti-chevron-down sidebar-chevron"></i>
            </div>
            <div class="sidebar-group-links" id="group-records">
                <a href="<?= url('requests') ?>" 
                   data-tooltip="All Requests"
                   class="<?= $uri === '/requests' ? 'active' : '' ?>">
                    <i class="ti ti-file-description"></i> <span>All Requests</span>
                </a>
                <?php if ($roleId === 1): ?>
                <a href="<?= url('employees') ?>"
                    data-tooltip="Employees"
                    class="<?= $uri === '/employees' ? 'active' : '' ?>">
                    <i class="ti ti-users"></i> <span>Employees</span>
                </a>
                <a href="<?= url('departments') ?>"
                    data-tooltip="Departments"
                    class="<?= $uri === '/departments' ? 'active' : '' ?>">
                    <i class="ti ti-building-community"></i> <span>Departments</span>
                </a>
                <a href="<?= url('companies') ?>"
                    data-tooltip="Companies"
                    class="<?= $uri === '/companies' ? 'active' : '' ?>">
                    <i class="ti ti-building-skyscraper"></i> <span>Companies</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="sidebar-group" data-group="extras">
            <div class="sidebar-label sidebar-label--toggle" data-toggle="extras">
                <i class="ti ti-sparkles"></i>
                <span>Extras</span>
                <i class="ti ti-chevron-down sidebar-chevron"></i>
            </div>
            <div class="sidebar-group-links" id="group-extras">
                <a href="<?= url('tools#services') ?>"
                   data-tooltip="Payslip"
                   class="<?= str_starts_with($uri, '/tools') ? 'active' : '' ?>">
                    <i class="ti ti-file-invoice"></i> <span>Payslip</span>
                </a>
                <?php if (in_array((int)($_SESSION['role_id'] ?? 0), [1, 6, 7], true)): ?>
                <a href="<?= url('tools#print-activities') ?>"
                   data-tooltip="Print Logs"
                         class="<?= str_starts_with($uri, '/tools') ? 'active' : '' ?>">
                    <i class="ti ti-printer"></i> <span>Print Logs</span>
                </a>
                <?php endif; ?>
                <a href="<?= url('tools#services') ?>"
                   data-tooltip="Leave Credits"
                   class="<?= str_starts_with($uri, '/tools') ? 'active' : '' ?>">
                    <i class="ti ti-calendar-stats"></i> <span>Leave Credits</span>
                </a>
                <a href="<?= url('tools#extras') ?>"
                   data-tooltip="World Clock"
                   class="<?= str_starts_with($uri, '/tools') ? 'active' : '' ?>">
                    <i class="ti ti-clock"></i> <span>World Clock</span>
                </a>
                <a href="<?= url('tools#extras') ?>"
                   data-tooltip="Calculator"
                   class="<?= str_starts_with($uri, '/tools') ? 'active' : '' ?>">
                    <i class="ti ti-calculator"></i> <span>Calculator</span>
                </a>
                <a href="<?= url('tools#extras') ?>"
                   data-tooltip="Notes"
                   class="<?= str_starts_with($uri, '/tools') ? 'active' : '' ?>">
                    <i class="ti ti-notes"></i> <span>Notes</span>
                </a>
                <a href="<?= url('tools#extras') ?>"
                   data-tooltip="Height & Weight"
                   class="<?= str_starts_with($uri, '/tools') ? 'active' : '' ?>">
                    <i class="ti ti-ruler-2"></i> <span>Height & Weight</span>
                </a>
            </div>
        </div>

    </div>

    <div class="sidebar-footer">
        <div class="user-block" id="userBlock">
            <!-- Popover floats above -->
            <div class="user-menu" id="userMenu">
                <a href="<?= url('profile') ?>" class="user-menu-item">
                    <i class="ti ti-user"></i> My Profile
                </a>
                <a href="<?= url('settings') ?>" class="user-menu-item">
                    <i class="ti ti-settings"></i> Settings
                </a>
                <div class="user-menu-divider"></div>
                <form method="POST" action="<?= url('logout') ?>" id="logoutForm" class="logout-form">
                    <?= \App\Helpers\Csrf::field() ?>
                    <button class="user-menu-item user-menu-item--danger" type="submit">
                        <i class="ti ti-logout"></i> Sign out
                    </button>
                </form>
            </div>
            <?php if ($sidebarAvatarUrl): ?>
                <img src="<?= $sidebarAvatarUrl ?>" class="user-avatar user-avatar-img" alt="">
            <?php else: ?> 
                <div class="user-avatar"><?= $initials ?></div>
            <?php endif; ?>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                <div class="user-role"><?= $roleName ?> · <?= htmlspecialchars($_SESSION['department'] ?? '') ?></div>
            </div>
            <i class="ti ti-chevron-up user-block-chevron" id="userBlockChevron"></i>
        </div>
    </div> 
</nav>