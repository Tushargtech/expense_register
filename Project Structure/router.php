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

    case 'logout':
        $auth->logout();
        break;

    default:
        header('Location: ?route=view2');
        exit;
}