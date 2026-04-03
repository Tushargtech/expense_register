<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

define('ROOT_PATH', __DIR__);

$dbConfig = require ROOT_PATH . '/configs/db.php';
$envConfig = require ROOT_PATH . '/configs/env.php';

spl_autoload_register(function (string $className): void {
	$directories = [
		ROOT_PATH . '/libraries/',
		ROOT_PATH . '/models/',
		ROOT_PATH . '/controllers/',
	];

	foreach ($directories as $directory) {
		$filePath = $directory . $className . '.php';
		if (is_file($filePath)) {
			require_once $filePath;
			return;
		}
	}
});

