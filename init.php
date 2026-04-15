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
require_once ROOT_PATH . '/models/BudgetCategoryModel.php';
require_once ROOT_PATH . '/models/BudgetModel.php';
require_once ROOT_PATH . '/models/BudgetMonitorModel.php';
require_once ROOT_PATH . '/models/ExpenseModel.php';
require_once ROOT_PATH . '/models/WorkflowModel.php';
require_once ROOT_PATH . '/libraries/FlashMessage.php';
require_once ROOT_PATH . '/libraries/RbacService.php';
require_once ROOT_PATH . '/controllers/AuthController.php';
require_once ROOT_PATH . '/controllers/UserController.php';
require_once ROOT_PATH . '/controllers/DepartmentController.php';
require_once ROOT_PATH . '/controllers/BudgetCategoryController.php';
require_once ROOT_PATH . '/controllers/BudgetController.php';
require_once ROOT_PATH . '/controllers/BudgetMonitorController.php';
require_once ROOT_PATH . '/controllers/ExpenseController.php';
require_once ROOT_PATH . '/controllers/WorkflowController.php';


