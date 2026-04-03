<?php

$route = trim((string) ($_GET['route'] ?? 'view2'));
$route = $route === '' ? 'view2' : $route;

$userModel = new User($dbConfig);
$authController = new AuthController($userModel);
$dashboardController = new DashboardController();

switch ($route) {
	case 'module-1':
		$dashboardController->index();
		break;

	case 'view2':
		$authController->showLogin();
		break;

	case 'auth':
		$authController->login();
		break;

	case 'logout':
		$authController->logout();
		break;

	default:
		http_response_code(404);
		echo '404 - Route not found';
		break;
}

