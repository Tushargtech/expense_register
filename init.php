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
		require_once ROOT_PATH . '/libraries/phpmailerautoloader.php';
	}
} else {
	require_once ROOT_PATH . '/libraries/phpmailerautoloader.php';
}

$envConfig = require ROOT_PATH . '/configs/env.php';
$dbConfig = $envConfig['db'];

require_once ROOT_PATH . '/models/authmodel.php';
require_once ROOT_PATH . '/models/usermodel.php';
require_once ROOT_PATH . '/models/departmentmodel.php';
require_once ROOT_PATH . '/models/budgetcategorymodel.php';
require_once ROOT_PATH . '/models/budgetmodel.php';
require_once ROOT_PATH . '/models/budgetmonitormodel.php';
require_once ROOT_PATH . '/models/expensemodel.php';
require_once ROOT_PATH . '/models/workflowmodel.php';
require_once ROOT_PATH . '/models/lookupmodel.php';
require_once ROOT_PATH . '/models/passwordresetmodel.php';
require_once ROOT_PATH . '/libraries/flashmessage.php';
require_once ROOT_PATH . '/libraries/rbacservice.php';
require_once ROOT_PATH . '/libraries/spreadsheetexportservice.php';
require_once ROOT_PATH . '/libraries/apirequest.php';
require_once ROOT_PATH . '/libraries/apiresponse.php';
require_once ROOT_PATH . '/libraries/mailservice.php';

// Temp file cleanup feature removed - files are now stored directly in upload folders

if (!function_exists('isApiRequestPath')) {
	function isApiRequestPath(): bool
	{
		$routeQuery = trim((string) ($_GET['route'] ?? ''), '/');
		if (str_starts_with($routeQuery, 'api/')) {
			return true;
		}

		$requestPath = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');

		if ($requestPath === '') {
			return false;
		}

		return str_contains($requestPath, '/api/') || str_starts_with($requestPath, 'api/');
	}
}

if (!function_exists('enforceAuthenticatedSessionTimeout')) {
	function enforceAuthenticatedSessionTimeout(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			return;
		}

		$timeoutMinutes = (int) ($GLOBALS['envConfig']['app']['session_timeout_minutes'] ?? SESSION_TIMEOUT_MINUTES);
		$timeoutSeconds = max(1, $timeoutMinutes) * 60;
		$lastActivityAt = (int) ($_SESSION['auth']['last_activity_at'] ?? 0);
		$currentTime = time();

		if ($lastActivityAt > 0 && ($currentTime - $lastActivityAt) > $timeoutSeconds) {
			unset($_SESSION['auth']);
			session_regenerate_id(true);

			if (!isApiRequestPath()) {
				flash_error('Your session expired due to inactivity. Please login again.');
			}

			return;
		}

		$_SESSION['auth']['last_activity_at'] = $currentTime;
	}
}

enforceAuthenticatedSessionTimeout();

require_once ROOT_PATH . '/controllers/apibasecontroller.php';
require_once ROOT_PATH . '/controllers/authcontroller.php';
require_once ROOT_PATH . '/controllers/passwordresetcontroller.php';
require_once ROOT_PATH . '/controllers/usercontroller.php';
require_once ROOT_PATH . '/controllers/departmentcontroller.php';
require_once ROOT_PATH . '/controllers/budgetcategorycontroller.php';
require_once ROOT_PATH . '/controllers/budgetcontroller.php';
require_once ROOT_PATH . '/controllers/budgetmonitorcontroller.php';
require_once ROOT_PATH . '/controllers/expensecontroller.php';
require_once ROOT_PATH . '/controllers/workflowcontroller.php';
require_once ROOT_PATH . '/controllers/apirouter.php';
require_once ROOT_PATH . '/controllers/api/authapicontroller.php';
require_once ROOT_PATH . '/controllers/api/userapicontroller.php';
require_once ROOT_PATH . '/controllers/api/departmentapicontroller.php';
require_once ROOT_PATH . '/controllers/api/budgetcategoryapicontroller.php';
require_once ROOT_PATH . '/controllers/api/budgetapicontroller.php';
require_once ROOT_PATH . '/controllers/api/budgetmonitorapicontroller.php';
require_once ROOT_PATH . '/controllers/api/expenseapicontroller.php';
require_once ROOT_PATH . '/controllers/api/workflowapicontroller.php';

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

if (!function_exists('buildAbsoluteUrl')) {

	function buildAbsoluteUrl(string $route, array $query = []): string
	{
		static $baseUrl = null;
		if ($baseUrl === null) {
			// Try to get APP_URL from config
			if (defined('APP_URL') && APP_URL !== '') {
				$baseUrl = rtrim(APP_URL, '/');
			} else {
				$baseUrl = null;
			}
		}
		
		$path = buildCleanRouteUrl($route, $query);
		
		// Ensure path starts with /
		if (strpos($path, '/') !== 0) {
			$path = '/' . $path;
		}
		
		if ($baseUrl !== null) {
			// Use the configured base URL
			return $baseUrl . $path;
		}
		
		// Fallback to dynamic detection from current request
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
		$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
		return "{$scheme}://{$host}{$path}";
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


