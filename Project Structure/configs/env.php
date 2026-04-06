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

if (!function_exists('getDB')) {
	function getDB(): PDO
	{
		return new PDO(
			'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
			DB_USER,
			DB_PASS,
			[
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			]
		);
	}
}

return [
	'app' => [
		'name' => 'Expense Register',
		'base_path' => '/expense_portal/Project Structure',
	],
	'db' => [
		'host' => '127.0.0.1',
		'port' => 3307,
		'database' => 'expense_register',
		'username' => 'root',
		'password' => '',
		'charset' => 'utf8mb4',
	],
];
