<?php

class UserController
{
	private function normalizeUserPayload(array $source): array
	{
		return [
			'name' => trim((string) ($source['name'] ?? '')),
			'email' => trim((string) ($source['email'] ?? '')),
			'role' => trim((string) ($source['role'] ?? 'employee')),
			'department_id' => (int) ($source['department_id'] ?? 0),
			'manager_id' => (int) ($source['manager_id'] ?? 0),
			'user_is_active' => (int) ($source['user_is_active'] ?? 1),
		];
	}

	private function isValidUserPayload(array $userData): bool
	{
		$allowedRoles = ['admin', 'hr', 'manager', 'employee'];

		return (
			$userData['name'] !== '' &&
			filter_var($userData['email'], FILTER_VALIDATE_EMAIL) !== false &&
			in_array($userData['role'], $allowedRoles, true) &&
			$userData['department_id'] > 0 &&
			$userData['manager_id'] > 0 &&
			in_array($userData['user_is_active'], [0, 1], true)
		);
	}

	private function ensureAuthenticated(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			$_SESSION['auth_error'] = 'Please login to continue.';
			header('Location: ?route=dashboard');
			exit;
		}
	}

	public function list(): void
	{
		$this->ensureAuthenticated();
		$sessionRole = (string) ($_SESSION['auth']['role'] ?? $_SESSION['role'] ?? '');
		$normalizedRole = strtolower(trim($sessionRole));
		$allowedRoles = ['admin', 'hr', 'employee', 'emp', 'manager', 'finance'];

		if ($normalizedRole !== '' && !in_array($normalizedRole, $allowedRoles, true)) {
			header('Location: ?route=home&error=unauthorized');
			exit;
		}

		$userModel = new UserModel();

		$filters = [
			'search' => trim((string) ($_GET['search'] ?? '')),
			'role' => trim((string) ($_GET['role'] ?? '')),
			'department' => trim((string) ($_GET['department'] ?? '')),
			'status' => trim((string) ($_GET['status'] ?? '')),
		];

		$perPage = 10;
		$currentPage = max(1, (int) ($_GET['page'] ?? 1));
		$totalUsers = $userModel->countAllUsers($filters);
		$totalPages = max(1, (int) ceil($totalUsers / $perPage));
		if ($currentPage > $totalPages) {
			$currentPage = $totalPages;
		}
		$offset = ($currentPage - 1) * $perPage;

		$users = $userModel->getAllUsers($filters, $perPage, $offset);
		$roleOptions = $userModel->getRoleOptions();
		$departmentOptions = $userModel->getDepartmentOptions();

		$pageTitle = 'User Management - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/list.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'user-list';

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/user_list.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}

	public function create(): void
	{
		$this->ensureAuthenticated();

		$deptModel = new DepartmentModel();
		$departments = $deptModel->getAllDepartments();
		$userModel = new UserModel();
		$managers = $userModel->getManagerOptions();

		$pageTitle = 'Add Employee - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/creation.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'user-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = false;
		$formAction = '?route=users/create';
		$formTitle = 'Add Employee';
		$submitLabel = 'Save Employee';
		$user = [
			'user_id' => 0,
			'user_name' => '',
			'user_email' => '',
			'user_role' => 'employee',
			'department_id' => 0,
			'manager_id' => 0,
			'user_is_active' => 1,
		];

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/user_create.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}

	public function edit(): void
	{
		$this->ensureAuthenticated();

		$userId = (int) ($_GET['id'] ?? 0);
		if ($userId <= 0) {
			header('Location: ?route=users&error=' . urlencode('Invalid user id'));
			exit;
		}

		$deptModel = new DepartmentModel();
		$departments = $deptModel->getAllDepartments();
		$userModel = new UserModel();
		$managers = $userModel->getManagerOptions();
		$user = $userModel->getUserById($userId);

		if ($user === null) {
			header('Location: ?route=users&error=' . urlencode('User not found'));
			exit;
		}

		$pageTitle = 'Edit Employee - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/creation.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'user-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = true;
		$formAction = '?route=users/edit&id=' . (int) $userId;
		$formTitle = 'Edit Employee';
		$submitLabel = 'Update Employee';

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/user_create.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}

	public function store(): void
	{
		$this->ensureAuthenticated();

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=users/create');
			exit;
		}

		$userData = $this->normalizeUserPayload($_POST);

		if (!$this->isValidUserPayload($userData)) {
			header('Location: ?route=users/create&error=' . urlencode('Please fill all required fields correctly.'));
			exit;
		}

		$userModel = new UserModel();
		$success = $userModel->createUser($userData);

		if ($success) {
			header('Location: ?route=users&success=' . urlencode('Employee created successfully.'));
		} else {
			header('Location: ?route=users/create&error=' . urlencode('Failed to create employee.'));
		}
		exit;
	}

	public function update(): void
	{
		$this->ensureAuthenticated();

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=users');
			exit;
		}

		$userId = (int) ($_GET['id'] ?? 0);
		if ($userId <= 0) {
			header('Location: ?route=users&error=' . urlencode('Invalid user id'));
			exit;
		}

		$userData = $this->normalizeUserPayload($_POST);

		if (!$this->isValidUserPayload($userData)) {
			header('Location: ?route=users/edit&id=' . $userId . '&error=' . urlencode('Please fill all required fields correctly.'));
			exit;
		}

		$userModel = new UserModel();
		$success = $userModel->updateUser($userId, $userData);

		if ($success) {
			header('Location: ?route=users&success=' . urlencode('Employee updated successfully.'));
		} else {
			header('Location: ?route=users/edit&id=' . $userId . '&error=' . urlencode('Failed to update employee.'));
		}
		exit;
	}
}

