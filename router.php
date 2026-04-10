<?php

$route = trim((string) ($_GET['route'] ?? 'dashboard'), '/');
$route = $route === '' ? 'dashboard' : $route;

$auth = new AuthController();

switch ($route) {
    case 'dashboard':
    case 'view2':
        $auth->showLogin();
        break;

    case 'auth':
        $auth->login();
        break;

    case 'home':
    case 'module-1':
        $auth->dashboard();
        break;

    case 'users':
    case '/users':
        $userController = new UserController();
        $userController->list();
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

    case 'workflows':
    case '/workflows':
        $workflowController = new WorkflowListController();
        $workflowController->list();
        break;

    case 'logout':
        $auth->logout();
        break;

    default:
        header('Location: ?route=dashboard');
        exit;
}