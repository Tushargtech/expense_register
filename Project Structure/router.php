<?php

$route = trim((string) ($_GET['route'] ?? 'dashboard'), '/');
$route = $route === '' ? 'dashboard' : $route;

$auth = new AuthController();

switch ($route) {
    case 'dashboard':
        $auth->showLogin();
        break;

    case 'auth':
        $auth->login();
        break;

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

    case 'logout':
        $auth->logout();
        break;

    default:
        header('Location: ?route=view2');
        exit;
}