<?php
    require_once __DIR__ . '/../app/Controllers/AuthController.php';
    require_once __DIR__ . '/../app/Controllers/SettingsController.php';
    require_once __DIR__ . '/../app/Controllers/FormController.php';
    require_once __DIR__ . '/../app/Controllers/ApprovalController.php';
    require_once __DIR__ . '/../app/Controllers/EmployeeController.php';

    require_once __DIR__ . '/../app/Controllers/DepartmentController.php';
    require_once __DIR__ . '/../app/Controllers/CompanyController.php';

    require_once __DIR__ . '/../app/Controllers/NotificationController.php';
    require_once __DIR__ . '/../app/Controllers/ToolsController.php';
    require_once __DIR__ . '/../app/Helpers/EmployeeCode.php';

    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // Strip the subpath prefix only when APP_URL includes one (non-Docker deploys).
    // In Docker, DocumentRoot is already /public so the URI is clean.
    $appPath = parse_url($_ENV['APP_URL'] ?? '', PHP_URL_PATH) ?? '';
    if ($appPath && $appPath !== '/') {
        $uri = str_replace(rtrim($appPath, '/'), '', $uri) ?: '/';
    }
    $method = $_SERVER['REQUEST_METHOD'];

    // Windows PrintService collector; protected by a dedicated bearer token.
    if ($uri === '/api/print-activities' && $method === 'POST') {
        (new ToolsController)->ingestPrintActivities();
        exit;
    }
    if ($uri === '/api/print-activities' && $method === 'GET') {
        (new ToolsController)->listPrintActivities();
        exit;
    }
    if ($uri === '/api/print-spool-archives' && $method === 'POST') {
        (new ToolsController)->ingestPrintSpoolArchive();
        exit;
    }
    if (preg_match('#^/tools/print-archives/(\d+)/(spool|pdf)$#', $uri, $archiveMatch) && $method === 'GET') {
        (new ToolsController)->downloadPrintSpoolArchive(
            (int)$archiveMatch[1],
            $archiveMatch[2] === 'pdf'
        );
        exit;
    }

    // PUBLIC - Auth routes (No auth required)
    if ($uri === '/login') {
        if ($method === 'POST') (new AuthController)->login();
        else require __DIR__ . '/../views/auth/login.php';
        exit;
    }

    if ($uri === '/forgot-password') {
        if ($method === 'POST') (new AuthController)->forgotPassword();
        else require __DIR__ . '/../views/auth/authchange/forgot_password.php';
        exit;
    }

    if ($uri === '/reset-password') {
        require __DIR__ . '/../views/auth/authchange/reset_password.php';
        exit;
    }

    if ($uri === '/update-password' && $method === 'POST') {
        (new AuthController)->updatePassword();
        exit;
    }
    
    // ---------------------------------------------------------------
    // AUTH GATGE = everything below requires login
    // ---------------------------------------------------------------
    \App\Middleware\AuthMiddleware::require();

    if ($uri === '/logout' && $method === 'POST') {
        (new AuthController)->logout();
        exit;
    }

    // Dashboard
    if ($uri === '/' || $uri === '/dashboard') {
        require __DIR__ . '/../views/layouts/dashboard.php';
        exit;
    }

    // ---------------------------------------------------------------
    // Forms
    // ---------------------------------------------------------------

    // Multilevel Approval Routes
   $approvalActions = ['submit', 'checker-approval', 'hr-verification', 'review-approval', 'process-approval', 'evaluation-approval', 'grant-approval', 'payment-proof'];

    foreach ($approvalActions as $action) {
        $safeAction = preg_quote($action, '#');
        if (preg_match("#^/forms/(\d+)/approve/{$safeAction}$#", $uri, $m) && $method === 'POST') {
            (new FormController)->approve((int)$m[1], $action);
            exit;
        }
    }

    // POST /forms/{id}/payment-proof
    if (preg_match('#^/forms/(\d+)/payment-proof$#', $uri, $m) && $method === 'POST') {
        (new FormController)->uploadPaymentProof((int)$m[1]);
        exit;
    }

    // POST /forms/{id}/liquidation-proof
    if (preg_match('#^/forms/(\d+)/liquidation-proof$#', $uri, $m) && $method === 'POST') {
        (new FormController)->uploadLiquidationProof((int)$m[1]);
        exit;
    }

     // POST /forms/{id}/reject
    if (preg_match('#^/forms/(\d+)/reject$#', $uri, $m) && $method === 'POST') {
        (new FormController)->reject((int)$m[1]);
        exit;
    }

    // GET /forms/{id}/edit
    if (preg_match('#^/forms/(\d+)/edit$#', $uri, $m) && $method === 'GET') {
        (new FormController)->edit((int)$m[1]);
        exit;
    }

    // POST /forms/{id}/update
    if (preg_match('#^/forms/(\d+)/update$#', $uri, $m) && $method === 'POST') {
        (new FormController)->update((int)$m[1]);
        exit;
    }

    // POST /forms/{id}/delete
    if (preg_match('#^/forms/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
        (new FormController)->delete((int)$m[1]);
        exit;
    }

    // GET /forms/view/{id}
    if (preg_match('#^/forms/view/(\d+)$#', $uri, $m)) {
        (new FormController)->show((int)$m[1]);
        exit;
    }

    // GET|POST /forms/{slug}/create
    if (preg_match('#^/forms/([\w-]+)/create$#', $uri, $m)) {
        (new FormController)->create($m[1]);
        exit;
    }

    // GET|POST /forms/{slug}
    if (preg_match('#^/forms/([\w-]+)$#', $uri, $m)) {
        if ($method === 'POST') {
            (new FormController)->create($m[1]);
        } else {
            (new FormController)->index($m[1]);
        }
        exit;
    }

    // ---------------------------------------------------------------
    // ADMIN
    // ---------------------------------------------------------------

    // employees
    if ($uri === '/employees') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new EmployeeController)->index();
        exit;
    }

    // GET|POST /employees/create
    if ($uri === '/employees/create') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new EmployeeController)->create();
        exit;
    }

    // GET /employees/edit/{id}
    if (preg_match('#^/employees/edit/(\d+)$#', $uri, $m)) {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new EmployeeController)->edit((int)$m[1]);
        exit;
    }

    // POST /employees/update/{id}
    if (preg_match('#^/employees/update/(\d+)$#', $uri, $m) && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new EmployeeController)->update((int)$m[1]);
        exit;
    }

    // POST /employees/{id}/delete
    if (preg_match('#^/employees/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new EmployeeController)->delete((int)$m[1]);
        exit;
    }

    // POST /employees/{id}/status
    if (preg_match('#^/employees/(\d+)/status$#', $uri, $m) && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new EmployeeController)->updateStatus((int)$m[1]);
        exit;
    }

    // POST /forms/{id}/admin-approve  and  /forms/{id}/admin-reject
    if (preg_match('#^/forms/(\d+)/admin-(approve|reject)$#', $uri, $m) && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new EmployeeController)->actAsApprover((int)$m[1], $m[2]  === 'approve' ? 'approved' : 'rejected');
        exit;
    }

    // ---------------------------------------------------------------
    // DEPARTMENTS (SysAdmin only)
    // ---------------------------------------------------------------
    if ($uri === '/departments') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new DepartmentController)->index();
        exit;
    }
    if ($uri === '/departments/create' && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new DepartmentController)->store();
        exit;
    }
    if (preg_match('#^/departments/(\d+)/update$#', $uri, $m) && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new DepartmentController)->update((int)$m[1]);
        exit;
    }
    if (preg_match('#^/departments/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new DepartmentController)->delete((int)$m[1]);
        exit;
    }
    if (preg_match('#^/departments/(\d+)/members$#', $uri, $m) && $method === 'GET') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new DepartmentController)->members((int)$m[1]);
        exit;
    }

    // ---------------------------------------------------------------
    // COMPANIES (SysAdmin only)
    // ---------------------------------------------------------------
    if ($uri === '/companies') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new CompanyController)->index();
        exit;
    }
    if ($uri === '/companies/create' && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new CompanyController)->store();
        exit;
    }
    if (preg_match('#^/companies/(\d+)/update$#', $uri, $m) && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new CompanyController)->update((int)$m[1]);
        exit;
    }
    if (preg_match('#^/companies/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new CompanyController)->delete((int)$m[1]);
        exit;
    }
    if (preg_match('#^/companies/(\d+)/members$#', $uri, $m) && $method === 'GET') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new CompanyController)->members((int)$m[1]);
        exit;
    }
    if (preg_match('#^/companies/(\d+)/logo$#', $uri, $m) && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new CompanyController)->uploadLogo((int)$m[1]);
        exit;
    }

    // ---------------------------------------------------------------
    // Profile
    // ---------------------------------------------------------------
    if ($uri === '/profile/avatar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        (new EmployeeController)->uploadAvatar();
        exit;
    }
    
    if ($uri === '/profile') {
        (new EmployeeController)->profile();
        exit;
    }

    // GET /approvals — pending approval inbox
    if ($uri === '/approvals') {
        \App\Middleware\RoleMiddleware::requireAnyRole([1, 2, 3, 4, 5, 6, 7, 8, 9]); // extend for roles 4,5,6 as needed
        (new \App\Controllers\ApprovalController)->inbox();
        exit;
    }

    // GET /my-submissions — current user's own forms
    if ($uri === '/my-submissions') {
        (new FormController)->mySubmissions();
        exit;
    }

    // GET /requests — all requests for privileged roles, otherwise process-scoped
    if ($uri === '/requests') {
        (new FormController)->allRequests();
        exit;
    }

    // GET /requests/export — completed request exports for privileged roles
    if ($uri === '/requests/export') {
        (new FormController)->exportCompletedRequests();
        exit;
    }

    // ---------------------------------------------------------------
    // Notifications
    // ---------------------------------------------------------------
    if ($uri === '/realtime/token' && $method === 'GET') {
        (new \App\Controllers\RealtimeController)->token();
        exit;
    }

    if ($uri === '/notifications/unread' && $method === 'GET') {
        (new \App\Controllers\NotificationController)->unread();
        exit;
    }

    if ($uri === '/notifications/read-all' && $method === 'POST') {
        (new \App\Controllers\NotificationController)->markAllRead();
        exit;
    }

    if (preg_match('#^/notifications/(\d+)/read$#', $uri, $m) && $method === 'POST') {
        (new \App\Controllers\NotificationController)->markRead((int)$m[1]);
        exit;
    }

    // ---------------------------------------------------------------
    // Settings (SysAdmin only)
    // ---------------------------------------------------------------
    if ($uri === '/settings') {
        // All authenticated users — tab visibility is controlled in the view
        (new SettingsController)->index();
        exit;
    }

    if ($uri === '/settings/general' && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new SettingsController)->updateGeneral();
        exit;
    }

    if ($uri === '/settings/mail' && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new SettingsController)->updateMail();
        exit;
    }

    if ($uri === '/settings/appearance' && $method === 'POST') {
        (new SettingsController)->updateAppearance();
        exit;
    }

    if ($uri === '/settings/notifications' && $method === 'POST') {
        (new SettingsController)->updateNotifications();
        exit;
    }

    if ($uri === '/settings/storage' && $method === 'POST') {
        \App\Middleware\RoleMiddleware::requireRole(1);
        (new SettingsController)->updateStorage();
        exit;
    }

    // ---------------------------------------------------------------
    // Chat / Messaging
    // ---------------------------------------------------------------
    require_once __DIR__ . '/../app/Controllers/ChatController.php';

    if ($uri === '/chat') {
        (new \App\Controllers\ChatController)->index();
        exit;
    }
    if ($uri === '/chat/users' && $method === 'GET') {
        (new \App\Controllers\ChatController)->users();
        exit;
    }
    if ($uri === '/chat/messages' && $method === 'GET') {
        (new \App\Controllers\ChatController)->messages();
        exit;
    }
    if ($uri === '/chat/send' && $method === 'POST') {
        (new \App\Controllers\ChatController)->send();
        exit;
    }
    if ($uri === '/chat/poll' && $method === 'GET') {
        (new \App\Controllers\ChatController)->poll();
        exit;
    }
    if ($uri === '/chat/unread' && $method === 'GET') {
        (new \App\Controllers\ChatController)->unread();
        exit;
    }
    if ($uri === '/chat/block' && $method === 'POST') {
        (new \App\Controllers\ChatController)->block();
        exit;
    }
    if ($uri === '/chat/unblock' && $method === 'POST') {
        (new \App\Controllers\ChatController)->unblock();
        exit;
    }

    if ($uri === '/chat/mark-read' && $method === 'POST') {
        (new \App\Controllers\ChatController)->markRead();
        exit;
    }

    if ($uri === '/chat/typing') {
        (new \App\Controllers\ChatController)->typing();
        exit;
    }

    if ($uri === '/chat/react' && $method === 'POST') {
        (new \App\Controllers\ChatController)->react();
        exit;
    }

    if ($uri === '/chat/delete' && $method === 'POST') {
        (new \App\Controllers\ChatController)->delete();
        exit;
    }
    
    if ($uri === '/chat/block-status' && $method === 'GET') {
        (new \App\Controllers\ChatController)->blockStatus();
        exit;
    }

    if ($uri === '/chat/upload' && $method === 'POST') {
        (new \App\Controllers\ChatController)->upload();
        exit;
    }

    if ($uri === '/chat/share-form' && $method === 'POST') {
        (new \App\Controllers\ChatController)->shareForm();
        exit;
    }

    // ---------------------------------------------------------------
    // Tools (World Clock, Calculator, Height/Weight Converter,
    // Notes, File Converter)
    // ---------------------------------------------------------------
    if ($uri === '/tools' && $method === 'GET') {
        (new ToolsController)->index();
        exit;
    }

    if ($uri === '/attendance' && $method === 'GET') {
        (new ToolsController)->attendance();
        exit;
    }

    if ($uri === '/tools/payslip/request' && $method === 'POST') {
        (new ToolsController)->requestPayslip();
        exit;
    }

    if ($uri === '/tools/notes' && $method === 'GET') {
        (new ToolsController)->listNotes();
        exit;
    }

    if ($uri === '/tools/notes' && $method === 'POST') {
        (new ToolsController)->createNote();
        exit;
    }

    if (preg_match('#^/tools/notes/(\d+)/update$#', $uri, $m) && $method === 'POST') {
        (new ToolsController)->updateNote((int)$m[1]);
        exit;
    }

    if (preg_match('#^/tools/notes/(\d+)/delete$#', $uri, $m) && $method === 'POST') {
        (new ToolsController)->deleteNote((int)$m[1]);
        exit;
    }

    // ---------------------------------------------------------------
    // 404
    // ---------------------------------------------------------------
    http_response_code(404);
    echo '<h3>404 - Page not found</h3>';