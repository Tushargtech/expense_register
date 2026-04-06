<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

define('ROOT_PATH', __DIR__);

$envConfig = require ROOT_PATH . '/configs/env.php';
$dbConfig = $envConfig['db'];

require_once ROOT_PATH . '/models/AuthModel.php';
require_once ROOT_PATH . '/models/UserModel.php';
require_once ROOT_PATH . '/models/DepartmentModel.php';
require_once ROOT_PATH . '/controllers/AuthController.php';
require_once ROOT_PATH . '/controllers/UserController.php';
