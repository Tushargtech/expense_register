<?php

if (PHP_SAPI === 'cli-server') {
    $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $staticFile = __DIR__ . $requestPath;

    if ($requestPath !== '/' && is_file($staticFile)) {
        $extension = strtolower((string) pathinfo($staticFile, PATHINFO_EXTENSION));
        $mimeMap = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'json' => 'application/json',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        header('Content-Type: ' . ($mimeMap[$extension] ?? (function_exists('mime_content_type') ? (string) mime_content_type($staticFile) : 'application/octet-stream')));
        header('Content-Length: ' . (string) filesize($staticFile));
        readfile($staticFile);
        exit;
    }
}

$routeParam = trim((string) ($_GET['route'] ?? ''), '/');

if ($routeParam === '') {
    $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    $normalizedPath = str_replace('\\', '/', $requestPath);

    if ($scriptDir !== '' && $scriptDir !== '.' && str_starts_with($normalizedPath, $scriptDir . '/')) {
        $normalizedPath = substr($normalizedPath, strlen($scriptDir) + 1);
    } elseif (str_starts_with($normalizedPath, '/')) {
        $normalizedPath = ltrim($normalizedPath, '/');
    }

    $route = trim($normalizedPath, '/');
} else {
    $route = $routeParam;
}

$route = trim((string) $route, '/');
$route = $route === '' ? 'login' : $route;

if (str_starts_with($route, 'api/v1/')) {
    $apiRouter = new ApiRouter();
    $apiRouter->dispatch(substr($route, strlen('api/v1/')));
    exit;
}

if (str_starts_with($route, 'api/')) {
    $apiRouter = new ApiRouter();
    $apiRouter->dispatch(substr($route, strlen('api/')));
    exit;
}

$auth = new AuthController();

switch ($route) {
    case 'login':
    case '/login':
    case 'view2':
        $auth->showLogin();
        break;

    case 'auth':
        $auth->login();
        break;

    case 'home':
    case '/home':
    case '/module-1':
    case 'module-1':
    case 'dashboard-view':
    case '/dashboard-view':
    case 'dashboard':
    case '/dashboard':
        $auth->dashboard();
        break;

    case 'forbidden':
    case '/forbidden':
        $auth->forbidden();
        break;

    case 'users':
    case '/users':
        $userController = new UserController();
        $userController->list();
        break;

    case 'my-profile':
    case '/my-profile':
        $userController = new UserController();
        $userController->myProfile();
        break;

    case 'users/create':
    case '/users/create':
        $userController = new UserController();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $userController->store();
        } else {
            $userController->create();
        }
        break;

    case 'users/edit':
    case '/users/edit':
        $userController = new UserController();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $userController->update();
        } else {
            $userController->edit();
        }
        break;

    case 'departments':
    case '/departments':
        $deptController = new DepartmentController();
        $deptController->list();
        break;

    case 'departments/create':
    case '/departments/create':
        $deptController = new DepartmentController();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $deptController->store();
        } else {
            $deptController->create();
        }
        break;

    case 'departments/edit':
    case '/departments/edit':
        $deptController = new DepartmentController();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $deptController->update();
        } else {
            $deptController->edit();
        }
        break;

    case 'budget-categories':
    case '/budget-categories':
        $budgetCategoryController = new BudgetCategoryController();
        $budgetCategoryController->list();
        break;

    case 'budget-categories/create':
    case '/budget-categories/create':
        $budgetCategoryController = new BudgetCategoryController();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $budgetCategoryController->store();
        } else {
            $budgetCategoryController->create();
        }
        break;

    case 'budget-categories/edit':
    case '/budget-categories/edit':
        $budgetCategoryController = new BudgetCategoryController();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $budgetCategoryController->update();
        } else {
            $budgetCategoryController->edit();
        }
        break;

    case 'budget-uploader':
    case '/budget-uploader':
        $budgetController = new BudgetController();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $budgetController->upload();
        } else {
            $budgetController->index();
        }
        break;

    case 'budgets/delete':
    case '/budgets/delete':
        $budgetController = new BudgetController();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $budgetController->delete();
        } else {
            header('Location: ?route=budget-monitor');
            exit;
        }
        break;

    case 'budget-monitor':
    case '/budget-monitor':
        $budgetMonitorController = new BudgetMonitorController();
        $budgetMonitorController->index();
        break;

    case 'expenses':
    case '/expenses':
        $expenseController = new ExpenseController();
        $expenseController->list();
        break;

    case 'expenses/create':
    case '/expenses/create':
        $expenseController = new ExpenseController();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $expenseController->store();
        } else {
            $expenseController->create();
        }
        break;

    case 'expenses/review':
    case '/expenses/review':
        $expenseController = new ExpenseController();
        $expenseController->review();
        break;

    case 'expenses/attachment/download':
    case '/expenses/attachment/download':
        $expenseController = new ExpenseController();
        $expenseController->downloadAttachment();
        break;

    case 'expenses/attachment/view':
    case '/expenses/attachment/view':
        $expenseController = new ExpenseController();
        $expenseController->viewAttachment();
        break;
    
    case 'workflows':
    case '/workflows':
        $workflowController = new WorkflowController();
        $workflowController->list();
        break;

    case 'workflows/create':
    case '/workflows/create':
        $workflowCreationController = new WorkflowController();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $workflowCreationController->store();
        } else {
            $workflowCreationController->create();
        }
        break;

    case 'workflows/edit':
    case '/workflows/edit':
        $workflowCreationController = new WorkflowController();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $workflowCreationController->update();
        } else {
            $workflowCreationController->edit();
        }
        break;

    case 'logout':
        $auth->logout();
        break;

    default:
        header('Location: ?route=login');
        exit;
}