<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

date_default_timezone_set('Asia/Kolkata');

session_start();

define('ROOT_PATH', __DIR__);

$composerAutoload = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
	require_once $composerAutoload;

	if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
		require_once ROOT_PATH . '/libraries/PHPMailerAutoloader.php';
	}
} else {
	require_once ROOT_PATH . '/libraries/PHPMailerAutoloader.php';
}

$envConfig = require ROOT_PATH . '/configs/env.php';
$dbConfig = $envConfig['db'];

require_once ROOT_PATH . '/models/AuthModel.php';
require_once ROOT_PATH . '/models/UserModel.php';
require_once ROOT_PATH . '/models/DepartmentModel.php';
require_once ROOT_PATH . '/models/BudgetCategoryModel.php';
require_once ROOT_PATH . '/models/BudgetModel.php';
require_once ROOT_PATH . '/models/BudgetMonitorModel.php';
require_once ROOT_PATH . '/models/ExpenseModel.php';
require_once ROOT_PATH . '/models/WorkflowModel.php';
require_once ROOT_PATH . '/models/LookupModel.php';
require_once ROOT_PATH . '/models/PasswordResetModel.php';
require_once ROOT_PATH . '/libraries/FlashMessage.php';
require_once ROOT_PATH . '/libraries/RbacService.php';
require_once ROOT_PATH . '/libraries/ApiRequest.php';
require_once ROOT_PATH . '/libraries/ApiResponse.php';
require_once ROOT_PATH . '/libraries/MailService.php';
require_once ROOT_PATH . '/controllers/ApiBaseController.php';
require_once ROOT_PATH . '/controllers/AuthController.php';
require_once ROOT_PATH . '/controllers/PasswordResetController.php';
require_once ROOT_PATH . '/controllers/UserController.php';
require_once ROOT_PATH . '/controllers/DepartmentController.php';
require_once ROOT_PATH . '/controllers/BudgetCategoryController.php';
require_once ROOT_PATH . '/controllers/BudgetController.php';
require_once ROOT_PATH . '/controllers/BudgetMonitorController.php';
require_once ROOT_PATH . '/controllers/ExpenseController.php';
require_once ROOT_PATH . '/controllers/WorkflowController.php';
require_once ROOT_PATH . '/controllers/ApiRouter.php';
require_once ROOT_PATH . '/controllers/Api/AuthApiController.php';
require_once ROOT_PATH . '/controllers/Api/UserApiController.php';
require_once ROOT_PATH . '/controllers/Api/DepartmentApiController.php';
require_once ROOT_PATH . '/controllers/Api/BudgetCategoryApiController.php';
require_once ROOT_PATH . '/controllers/Api/BudgetApiController.php';
require_once ROOT_PATH . '/controllers/Api/BudgetMonitorApiController.php';
require_once ROOT_PATH . '/controllers/Api/ExpenseApiController.php';
require_once ROOT_PATH . '/controllers/Api/WorkflowApiController.php';

if (!function_exists('buildCleanRouteUrl')) {
	function buildCleanRouteUrl(string $route, array $query = []): string
	{
		$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
		$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
		$route = trim($route, '/');
		if ($route === '') {
			$route = 'login';
		}

		$path = ($basePath === '' || $basePath === '.') ? '/' . $route : $basePath . '/' . $route;
		$queryString = http_build_query($query);

		return $queryString !== '' ? ($path . '?' . $queryString) : $path;
	}
}

if (!function_exists('legacyRouteSpecToCleanUrl')) {
	function legacyRouteSpecToCleanUrl(string $routeSpec): string
	{
		$routeSpec = html_entity_decode(trim($routeSpec), ENT_QUOTES, 'UTF-8');
		if ($routeSpec === '') {
			return buildCleanRouteUrl('login');
		}

		$route = $routeSpec;
		$query = [];
		if (str_contains($routeSpec, '&')) {
			[$route, $rawQuery] = explode('&', $routeSpec, 2);
			parse_str($rawQuery, $query);
		}

		return buildCleanRouteUrl(trim($route, '/'), $query);
	}
}

if (function_exists('header_register_callback') && !defined('ROUTE_REDIRECT_NORMALIZER_ENABLED')) {
	define('ROUTE_REDIRECT_NORMALIZER_ENABLED', true);

	header_register_callback(static function (): void {
		$headers = headers_list();
		$locationHeader = null;

		foreach ($headers as $headerLine) {
			if (stripos($headerLine, 'Location: ') === 0) {
				$locationHeader = trim(substr($headerLine, strlen('Location: ')));
				break;
			}
		}

		if ($locationHeader === null || !str_starts_with($locationHeader, '?route=')) {
			return;
		}

		$queryString = substr($locationHeader, 1);
		parse_str($queryString, $params);
		$route = trim((string) ($params['route'] ?? 'login'));
		unset($params['route']);

		header_remove('Location');
		header('Location: ' . buildCleanRouteUrl($route, $params), true);
	});
}

if (!defined('ROUTE_HTML_LINK_REWRITE_ENABLED') && PHP_SAPI !== 'cli') {
	define('ROUTE_HTML_LINK_REWRITE_ENABLED', true);

	ob_start(static function (string $buffer): string {
		if ($buffer === '' || !str_contains($buffer, '?route=')) {
			return $buffer;
		}

		$pattern = '/\b(href|action)=(["\"])\?route=([^"\"])++\2/i';

		return preg_replace_callback($pattern, static function (array $matches): string {
			$attribute = (string) ($matches[1] ?? 'href');
			$quote = (string) ($matches[2] ?? '"');
			$routeSpec = (string) ($matches[3] ?? 'login');
			$cleanUrl = legacyRouteSpecToCleanUrl($routeSpec);
			$escapedUrl = htmlspecialchars($cleanUrl, ENT_QUOTES, 'UTF-8');

			return $attribute . '=' . $quote . $escapedUrl . $quote;
		}, $buffer) ?? $buffer;
	});
}


