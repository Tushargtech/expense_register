<?php

if (!defined('DB_HOST')) {
	define('DB_HOST', '127.0.0.1');
}
if (!defined('DB_NAME')) {
	define('DB_NAME', 'expense_register');
}
if (!defined('DB_USER')) {
	define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
	define('DB_PASS', '');
}
if (!defined('DB_PORT')) {
	define('DB_PORT', '3307');
}
if (!defined('MAIL_HOST')) {
	define('MAIL_HOST', 'smtp.gmail.com');
}
if (!defined('MAIL_PORT')) {
	define('MAIL_PORT', 587);
}
if (!defined('MAIL_ENCRYPTION')) {
	define('MAIL_ENCRYPTION', 'tls');
}
if (!defined('MAIL_USERNAME')) {
	define('MAIL_USERNAME', 'expenseregisterofficial@gmail.com');
}
if (!defined('MAIL_PASSWORD')) {
	define('MAIL_PASSWORD', 'vaub vyrf vohp vwqg');
}
if (!defined('MAIL_FROM_ADDRESS')) {
	define('MAIL_FROM_ADDRESS', 'expenseregisterofficial@gmail.com');
}
if (!defined('MAIL_FROM_NAME')) {
	define('MAIL_FROM_NAME', 'Expense Register Team');
}
if (!defined('SESSION_TIMEOUT_MINUTES')) {
	define('SESSION_TIMEOUT_MINUTES', 30);
}
if (!defined('AUTH_MAX_LOGIN_ATTEMPTS')) {
	define('AUTH_MAX_LOGIN_ATTEMPTS', 5);
}
if (!defined('AUTH_LOCKOUT_MINUTES')) {
	define('AUTH_LOCKOUT_MINUTES', 15);
}
if (!defined('FILE_UPLOAD_MAX_SIZE_MB')) {
	define('FILE_UPLOAD_MAX_SIZE_MB', 5);
}

if (!function_exists('getDB')) {
	function getDB(): PDO
	{
		$pdoOptions = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		];

		if (defined('Pdo\\Mysql::ATTR_INIT_COMMAND')) {
			$pdoOptions[Pdo\Mysql::ATTR_INIT_COMMAND] = "SET time_zone = '+05:30'";
		} else {
			$pdoOptions[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET time_zone = '+05:30'";
		}

		return new PDO(
			'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
			DB_USER,
			DB_PASS,
			$pdoOptions
		);
	}
}

return [
	'app' => [
		'name' => 'Expense Register',
		'base_path' => '/expense_portal',
		'session_timeout_minutes' => SESSION_TIMEOUT_MINUTES,
		'auth_max_login_attempts' => AUTH_MAX_LOGIN_ATTEMPTS,
		'auth_lockout_minutes' => AUTH_LOCKOUT_MINUTES,
		'file_upload_max_size_mb' => FILE_UPLOAD_MAX_SIZE_MB,
	],
	'db' => [
		'host' => '127.0.0.1',
		'port' => 3307,
		'database' => 'expense_register',
		'username' => 'root',
		'password' => '',
		'charset' => 'utf8mb4',
	],
	'mail' => [
		'host' => MAIL_HOST,
		'port' => MAIL_PORT,
		'encryption' => MAIL_ENCRYPTION,
		'username' => MAIL_USERNAME,
		'password' => MAIL_PASSWORD,
		'from_address' => MAIL_FROM_ADDRESS,
		'from_name' => MAIL_FROM_NAME,
	],
];
