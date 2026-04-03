<?php

/**
 * Environment defaults for local AMPPS setup.
 *
 * Update these values if your AMPPS MySQL settings are different.
 */
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
