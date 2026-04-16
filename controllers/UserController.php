<?php

class UserController
{
	private function rbac(): RbacService
	{
		return new RbacService();
	}

	private function ensureUserCrudAccess(): void
	{
		$this->ensureAuthenticated();
		if (!$this->rbac()->canManageUsers()) {
			header('Location: ?route=forbidden&code=rbac_user_crud');
			exit;
		}
	}

	private function ensureUserListAccess(): void
	{
		$this->ensureAuthenticated();
		if (!$this->rbac()->canViewUsers()) {
			header('Location: ?route=forbidden&code=rbac_user_list');
			exit;
		}
	}

	private function normalizeUserPayload(array $source): array
	{
		return [
			'name' => trim((string) ($source['name'] ?? '')),
			'email' => trim((string) ($source['email'] ?? '')),
			'role' => $this->normalizeUserRole((string) ($source['role'] ?? 'employee')),
			'department_id' => (int) ($source['department_id'] ?? 0),
			'manager_id' => (int) ($source['manager_id'] ?? 0),
			'user_is_active' => (int) ($source['user_is_active'] ?? 1),
		];
	}

	private function normalizeUserRole(string $role): string
	{
		$normalizedRole = strtolower(trim($role));

		if ($normalizedRole === 'emp') {
			return 'employee';
		}

		if (in_array($normalizedRole, ['dept_head', 'depthead', 'department_head', 'manager'], true)) {
			return 'employee';
		}

		if (in_array($normalizedRole, ['hr_manager', 'hr_department_head', 'hr_dept_head'], true)) {
			return 'hr';
		}

		return in_array($normalizedRole, ['admin', 'finance', 'hr', 'employee'], true) ? $normalizedRole : 'employee';
	}

	private function isValidUserPayload(array $userData): bool
	{
		$lookupModel = new LookupModel();
		$allowedRoles = $lookupModel->getRoleSlugs();
		$normalizedRole = $this->normalizeUserRole((string) ($userData['role'] ?? ''));

		return (
			$userData['name'] !== '' &&
			filter_var($userData['email'], FILTER_VALIDATE_EMAIL) !== false &&
			in_array($normalizedRole, $allowedRoles, true) &&
			$userData['department_id'] > 0 &&
			$userData['manager_id'] > 0 &&
			in_array($userData['user_is_active'], [0, 1], true)
		);
	}

	private function ensureAuthenticated(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			flash_error('Please login to continue.');
			header('Location: ?route=dashboard');
			exit;
		}
	}

	public function list(): void
	{
		$this->ensureUserListAccess();

		$userModel = new UserModel();
		$rbac = $this->rbac();

		$filters = [
			'search' => trim((string) ($_GET['search'] ?? '')),
			'role' => trim((string) ($_GET['role'] ?? '')),
			'department' => trim((string) ($_GET['department'] ?? '')),
			'status' => trim((string) ($_GET['status'] ?? '')),
			'department_id_scope' => 0,
		];

		$canViewAllUsers = $rbac->canViewAllUsers();
		if ($rbac->isDepartmentScopedUserViewer()) {
			$departmentIdScope = $rbac->departmentId();
			if ($departmentIdScope <= 0) {
				header('Location: ?route=forbidden&code=rbac_user_department_scope_missing');
				exit;
			}

			$filters['department_id_scope'] = $departmentIdScope;
			$filters['department'] = '';
		}

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
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'user-list';
		$canManageUsers = $rbac->canManageUsers();
		$canFilterByDepartment = $canViewAllUsers;

		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle,
			'pageStyles' => $pageStyles,
			'activeMenu' => $activeMenu,
		]);
		require ROOT_PATH . '/views/UserManagement/user_list.php';
		renderAppLayoutEnd();
	}

	public function create(): void
	{
		$this->ensureUserCrudAccess();

		$deptModel = new DepartmentModel();
		$departments = $deptModel->getAllDepartments();
		$userModel = new UserModel();
		$managers = $userModel->getManagerOptions();
		$roleOptions = $userModel->getRoleOptions();

		$pageTitle = 'Add Employee - Expense Register';
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'user-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = false;
		$formAction = buildCleanRouteUrl('users/create');
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

		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle,
			'pageStyles' => $pageStyles,
			'activeMenu' => $activeMenu,
		]);
		require ROOT_PATH . '/views/UserManagement/user_create.php';
		renderAppLayoutEnd();
	}

	public function edit(): void
	{
		$this->ensureUserCrudAccess();

		$userId = (int) ($_GET['id'] ?? 0);
		if ($userId <= 0) {
			flash_error('Invalid user id');
			header('Location: ?route=users');
			exit;
		}

		$deptModel = new DepartmentModel();
		$departments = $deptModel->getAllDepartments();
		$userModel = new UserModel();
		$managers = $userModel->getManagerOptions();
		$roleOptions = $userModel->getRoleOptions();
		$user = $userModel->getUserById($userId);

		if ($user === null) {
			flash_error('User not found');
			header('Location: ?route=users');
			exit;
		}

		$pageTitle = 'Edit Employee - Expense Register';
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'user-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = true;
		$formAction = buildCleanRouteUrl('users/edit', ['id' => (int) $userId]);
		$formTitle = 'Edit Employee';
		$submitLabel = 'Update Employee';

		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle,
			'pageStyles' => $pageStyles,
			'activeMenu' => $activeMenu,
		]);
		require ROOT_PATH . '/views/UserManagement/user_create.php';
		renderAppLayoutEnd();
	}

	public function store(): void
	{
		$this->ensureUserCrudAccess();

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ' . buildCleanRouteUrl('users/create'));
			exit;
		}

		$userData = $this->normalizeUserPayload($_POST);

		if (!$this->isValidUserPayload($userData)) {
			flash_error('Please fill all required fields correctly.');
			header('Location: ' . buildCleanRouteUrl('users/create'));
			exit;
		}

		$userModel = new UserModel();
		$success = $userModel->createUser($userData);

		if ($success) {
			RbacService::audit('user_create', ['email' => $userData['email'], 'role' => $userData['role']]);
			flash_success('Employee created successfully.');
			header('Location: ' . buildCleanRouteUrl('users'));
		} else {
			flash_error('Failed to create employee.');
			header('Location: ' . buildCleanRouteUrl('users/create'));
		}
		exit;
	}

	public function update(): void
	{
		$this->ensureUserCrudAccess();

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ' . buildCleanRouteUrl('users'));
			exit;
		}

		$userId = (int) ($_GET['id'] ?? 0);
		if ($userId <= 0) {
			flash_error('Invalid user id');
			header('Location: ' . buildCleanRouteUrl('users'));
			exit;
		}

		$userModel = new UserModel();
		$existingUser = $userModel->getUserById($userId);
		if ($existingUser === null) {
			flash_error('User not found.');
			header('Location: ' . buildCleanRouteUrl('users'));
			exit;
		}
		$userData = $this->normalizeUserPayload($_POST);

		if (!$this->isValidUserPayload($userData)) {
			flash_error('Please fill all required fields correctly.');
			header('Location: ' . buildCleanRouteUrl('users/edit', ['id' => $userId]));
			exit;
		}

		$success = $userModel->updateUser($userId, $userData);

		if ($success) {
			RbacService::audit('user_update', ['user_id' => $userId, 'role' => $userData['role']]);
			$oldRole = strtolower(trim((string) ($existingUser['user_role'] ?? '')));
			$newRole = strtolower(trim((string) ($userData['role'] ?? '')));
			if ($oldRole !== '' && $newRole !== '' && $oldRole !== $newRole) {
				RbacService::audit('user_role_change', ['user_id' => $userId, 'from' => $oldRole, 'to' => $newRole]);
			}
			flash_success('Employee updated successfully.');
			header('Location: ' . buildCleanRouteUrl('users'));
		} else {
			flash_error('Failed to update employee.');
			header('Location: ' . buildCleanRouteUrl('users/edit', ['id' => $userId]));
		}
		exit;
	}
}

