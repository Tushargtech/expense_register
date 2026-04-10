<?php


class DepartmentController
{
	
	private function isAuthorizedForDepartmentAccess(): bool
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			return false;
		}
		$userRole = (string) ($_SESSION['auth']['role'] ?? $_SESSION['role'] ?? '');
		$normalizedRole = strtolower(trim($userRole));
		return in_array($normalizedRole, ['admin', 'hr'], true);
	}

	
	public function list(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			$_SESSION['auth_error'] = 'Please login to continue.';
			header('Location: ?route=dashboard');
			exit;
		}
		if (!$this->isAuthorizedForDepartmentAccess()) {
			header('Location: ?route=home&error=' . urlencode('access_denied'));
			exit;
		}
		$deptModel = new DepartmentModel();
		$allDepartments = $deptModel->getAllDepartments();
		$filters = [
			'search' => trim((string) ($_GET['search'] ?? '')),
		];
		
		$searchValue = $filters['search'];
		$filteredDepartments = $allDepartments;
		
		if (!empty($searchValue)) {
			$searchLower = strtolower($searchValue);
			$filteredDepartments = array_filter($allDepartments, function($dept) use ($searchLower) {
				$code = strtolower((string) ($dept['department_code'] ?? ''));
				$name = strtolower((string) ($dept['department_name'] ?? ''));
				return strpos($code, $searchLower) !== false || strpos($name, $searchLower) !== false;
			});
		}
		$perPage = 10;
		$currentPage = max(1, (int) ($_GET['page'] ?? 1));
		$totalDepartments = count($filteredDepartments);
		$totalPages = max(1, (int) ceil($totalDepartments / $perPage));
		
		if ($currentPage > $totalPages) {
			$currentPage = $totalPages;
		}
		
		$offset = ($currentPage - 1) * $perPage;
		$departments = array_slice($filteredDepartments, $offset, $perPage);
		$pageTitle = 'Department Management - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/list.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'department-list';
		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/department_list.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}

	
	public function create(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			$_SESSION['auth_error'] = 'Please login to continue.';
			header('Location: ?route=dashboard');
			exit;
		}

		if (!$this->isAuthorizedForDepartmentAccess()) {
			header('Location: ?route=home&error=' . urlencode('access_denied'));
			exit;
		}
		$userModel = new UserModel();
		$managers = $userModel->getManagerOptions();
		$pageTitle = 'Create Department - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/creation.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'department-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = false; 
		$formAction = '?route=departments/create'; 
		$formTitle = 'Create Department';
		$submitLabel = 'Create Department';
		$department = [
			'id' => 0,
			'department_name' => '',
			'department_code' => '',
			'department_head_user_id' => 0,
		];
		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/department_creation.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}

	
	public function edit(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			$_SESSION['auth_error'] = 'Please login to continue.';
			header('Location: ?route=dashboard');
			exit;
		}

		if (!$this->isAuthorizedForDepartmentAccess()) {
			header('Location: ?route=home&error=' . urlencode('access_denied'));
			exit;
		}
		$deptId = (int) ($_GET['id'] ?? 0);
		if ($deptId <= 0) {
			header('Location: ?route=departments&error=' . urlencode('Invalid department ID'));
			exit;
		}
		$deptModel = new DepartmentModel();
		$department = $deptModel->getDepartmentById($deptId);
		if ($department === null) {
			header('Location: ?route=departments&error=' . urlencode('Department not found'));
			exit;
		}
		$userModel = new UserModel();
		$managers = $userModel->getManagerOptions();
		$pageTitle = 'Edit Department - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/creation.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'department-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = true; 
		$formAction = '?route=departments/edit&id=' . $deptId; 
		$formTitle = 'Edit Department';
		$submitLabel = 'Update Department';
		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/department_creation.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}

	
	private function isValidDepartmentPayload(array $departmentData): bool
	{
		return (
			!empty($departmentData['department_name']) &&
			!empty($departmentData['department_code']) &&
			isset($departmentData['department_head_user_id'])
		);
	}

	
	private function normalizeDepartmentPayload(array $source): array
	{
		return [
			'department_name' => trim((string) ($source['department_name'] ?? '')),
			'department_code' => trim((string) ($source['department_code'] ?? '')),
			'department_head_user_id' => (int) ($source['department_head_user_id'] ?? 0),
		];
	}

	
	public function store(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			$_SESSION['auth_error'] = 'Please login to continue.';
			header('Location: ?route=dashboard');
			exit;
		}

		if (!$this->isAuthorizedForDepartmentAccess()) {
			header('Location: ?route=home&error=' . urlencode('access_denied'));
			exit;
		}
		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=departments/create');
			exit;
		}
		$departmentData = $this->normalizeDepartmentPayload($_POST);

		if (!$this->isValidDepartmentPayload($departmentData)) {
			header('Location: ?route=departments/create&error=' . urlencode('Please fill all required fields correctly.'));
			exit;
		}
		$deptModel = new DepartmentModel();
		$success = $deptModel->createDepartment($departmentData);
		if ($success) {
			header('Location: ?route=departments&success=' . urlencode('Department created successfully.'));
		} else {
			header('Location: ?route=departments/create&error=' . urlencode('Failed to create department.'));
		}
		exit;
	}

	
	public function update(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			$_SESSION['auth_error'] = 'Please login to continue.';
			header('Location: ?route=dashboard');
			exit;
		}

		if (!$this->isAuthorizedForDepartmentAccess()) {
			header('Location: ?route=home&error=' . urlencode('access_denied'));
			exit;
		}
		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=departments');
			exit;
		}
		$deptId = (int) ($_GET['id'] ?? 0);
		if ($deptId <= 0) {
			header('Location: ?route=departments&error=' . urlencode('Invalid department id'));
			exit;
		}
		$departmentData = $this->normalizeDepartmentPayload($_POST);

		if (!$this->isValidDepartmentPayload($departmentData)) {
			header('Location: ?route=departments/edit&id=' . $deptId . '&error=' . urlencode('Please fill all required fields correctly.'));
			exit;
		}
		$deptModel = new DepartmentModel();
		$success = $deptModel->updateDepartment($deptId, $departmentData);
		if ($success) {
			header('Location: ?route=departments&success=' . urlencode('Department updated successfully.'));
		} else {
			header('Location: ?route=departments/edit&id=' . $deptId . '&error=' . urlencode('Failed to update department.'));
		}
		exit;
	}
}
