<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

define('ROOT_PATH', __DIR__);

$dbConfig = require ROOT_PATH . '/configs/db.php';

/**
 * Autoload classes from Core (/classes), Models (/models), and Controllers (/controllers).
 *
 * File-to-file flow:
 * - index.php -> init.php (autoload + DB setup)
 * - router.php creates controller instances
 * - controllers use models
 * - models use shared PDO connection
 */
spl_autoload_register(static function (string $className): void {
	$directories = [
		ROOT_PATH . '/classes/',
		ROOT_PATH . '/models/',
		ROOT_PATH . '/controllers/',
	];

	foreach ($directories as $directory) {
		$file = $directory . $className . '.php';
		if (is_file($file)) {
			require_once $file;
			return;
		}
	}
});

/**
 * Shared PDO instance used by controllers/models.
 */
try {
	$db = Database::getInstance($dbConfig)->getConnection();
} catch (PDOException $exception) {
	http_response_code(500);
	echo '<h2>Database connection failed</h2>';
	echo '<p>Please check your MySQL settings in configs/env.php.</p>';
	echo '<ul>';
	echo '<li>Host: ' . htmlspecialchars((string) $dbConfig['host'], ENT_QUOTES, 'UTF-8') . '</li>';
	echo '<li>Port: ' . htmlspecialchars((string) $dbConfig['port'], ENT_QUOTES, 'UTF-8') . '</li>';
	echo '<li>Database: ' . htmlspecialchars((string) $dbConfig['database'], ENT_QUOTES, 'UTF-8') . '</li>';
	echo '<li>User: ' . htmlspecialchars((string) $dbConfig['username'], ENT_QUOTES, 'UTF-8') . '</li>';
	echo '</ul>';
	echo '<p>Driver message: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
	exit;
}

