<?php

$route = trim((string) ($_GET['route'] ?? 'view2'));
$route = $route === '' ? 'view2' : $route;

$auth = new AuthController();

switch ($route) {
    case 'view2':
        $auth->showLogin();
        break;

    case 'auth':
        $auth->login();
        break;

    case 'module-1':
        $auth->dashboard();
        break;

    case 'logout':
        $auth->logout();
        break;

    default:
        header('Location: ?route=view2');
        exit;
}