<?php

$route = trim((string) ($_GET['route'] ?? 'login'));
$route = $route === '' ? 'login' : $route;

$authController = new AuthController(new AuthModel($db));
$isLoggedIn = $authController->isAuthenticated();

if ($route === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$authController->login($_POST);
}

switch ($route) {
	case 'login':
		if ($isLoggedIn) {
			header('Location: ?route=dashboard');
			exit;
		}
		$authController->showLogin();
		break;

	case 'dashboard':
		$authController->dashboard();
		break;

	case 'logout':
		$authController->logout();
		break;

	default:
		http_response_code(404);
		echo '404 - Route not found';
		break;
}

