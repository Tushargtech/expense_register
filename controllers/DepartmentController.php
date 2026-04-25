<?php


class DepartmentController
{
	private function rbac(): RbacService
	{
		return new RbacService();
	}

	private function ensureDepartmentCrudAccess(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			flash_error('Please login to continue.');
			header('Location: ?route=dashboard');
			exit;
		}

		if (!$this->rbac()->canManageDepartments()) {
			header('Location: ?route=forbidden&code=rbac_department_crud');
			exit;
		}
	}

	private function ensureDepartmentListAccess(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			flash_error('Please login to continue.');
			header('Location: ?route=dashboard');
			exit;
		}

		if (!$this->rbac()->canViewDepartments()) {
			header('Location: ?route=forbidden&code=rbac_department_list');
			exit;
		}
	}
	
	private function isAuthorizedForDepartmentAccess(): bool
	{
		return $this->rbac()->canViewDepartments();
	}

	
	public function list(): void
	{
		$this->ensureDepartmentListAccess();
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
		if (!empty($_GET['download'])) {
			$exportRows = [];
			foreach ($filteredDepartments as $department) {
				$exportRows[] = [
					(string) ($department['department_code'] ?? ''),
					(string) ($department['department_name'] ?? ''),
					(string) ($department['head_name'] ?? '-'),
					(string) ($department['head_email'] ?? '-'),
				];
			}

			$exportService = new SpreadsheetExportService();
			$exportService->streamXlsx(
				'departments-' . date('YmdHis') . '.xlsx',
				['Department Code', 'Department Name', 'Department Head', 'Head Email'],
				$exportRows,
				'Departments'
			);
		}
		$totalPages = max(1, (int) ceil($totalDepartments / $perPage));
		
		if ($currentPage > $totalPages) {
			$currentPage = $totalPages;
		}
		
		$offset = ($currentPage - 1) * $perPage;
		$departments = array_slice($filteredDepartments, $offset, $perPage);
		$pageTitle = 'Department Management - Expense Register';
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'department-list';
		$canManageDepartments = $this->rbac()->canManageDepartments();
		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle,
			'pageStyles' => $pageStyles,
			'activeMenu' => $activeMenu,
		]);
		require ROOT_PATH . '/views/DepartmentManagement/department_list.php';
		renderAppLayoutEnd();
	}

	
	public function create(): void
	{
		$this->ensureDepartmentCrudAccess();
		$userModel = new UserModel();
		$managers = $userModel->getManagerOptions();
		$pageTitle = 'Create Department - Expense Register';
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'department-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = false; 
		$formAction = buildCleanRouteUrl('departments/create'); 
		$formTitle = 'Create Department';
		$submitLabel = 'Create Department';
		$department = [
			'id' => 0,
			'department_name' => '',
			'department_code' => '',
			'department_head_user_id' => 0,
		];
		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle,
			'pageStyles' => $pageStyles,
			'activeMenu' => $activeMenu,
		]);
		require ROOT_PATH . '/views/DepartmentManagement/department_creation.php';
		renderAppLayoutEnd();
	}

	
	public function edit(): void
	{
		$this->ensureDepartmentCrudAccess();
		$deptId = (int) ($_GET['id'] ?? 0);
		if ($deptId <= 0) {
			flash_error('Invalid department ID');
			header('Location: ?route=departments');
			exit;
		}
		$deptModel = new DepartmentModel();
		$department = $deptModel->getDepartmentById($deptId);
		if ($department === null) {
			flash_error('Department not found');
			header('Location: ?route=departments');
			exit;
		}
		$userModel = new UserModel();
		$managers = $userModel->getManagerOptions();
		$pageTitle = 'Edit Department - Expense Register';
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'department-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = true; 
		$formAction = buildCleanRouteUrl('departments/edit', ['id' => $deptId]); 
		$formTitle = 'Edit Department';
		$submitLabel = 'Update Department';
		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle,
			'pageStyles' => $pageStyles,
			'activeMenu' => $activeMenu,
		]);
		require ROOT_PATH . '/views/DepartmentManagement/department_creation.php';
		renderAppLayoutEnd();
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
		$this->ensureDepartmentCrudAccess();
		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=departments/create');
			exit;
		}
		$departmentData = $this->normalizeDepartmentPayload($_POST);

		if (!$this->isValidDepartmentPayload($departmentData)) {
			flash_error('Please fill all required fields correctly.');
			header('Location: ?route=departments/create');
			exit;
		}
		$deptModel = new DepartmentModel();
		$success = $deptModel->createDepartment($departmentData);
		if ($success) {
			RbacService::audit('department_create', ['department_code' => $departmentData['department_code']]);
			flash_success('Department created successfully.');
			header('Location: ?route=departments');
		} else {
			$errorMessage = trim((string) ($deptModel->getLastValidationError() ?? ''));
			flash_error($errorMessage !== '' ? $errorMessage : 'Failed to create department.');
			header('Location: ?route=departments/create');
		}
		exit;
	}

	
	public function update(): void
	{
		$this->ensureDepartmentCrudAccess();
		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=departments');
			exit;
		}
		$deptId = (int) ($_GET['id'] ?? 0);
		if ($deptId <= 0) {
			flash_error('Invalid department id');
			header('Location: ?route=departments');
			exit;
		}
		$departmentData = $this->normalizeDepartmentPayload($_POST);

		if (!$this->isValidDepartmentPayload($departmentData)) {
			flash_error('Please fill all required fields correctly.');
			header('Location: ?route=departments/edit&id=' . $deptId);
			exit;
		}
		$deptModel = new DepartmentModel();
		$success = $deptModel->updateDepartment($deptId, $departmentData);
		if ($success) {
			RbacService::audit('department_update', ['department_id' => $deptId]);
			flash_success('Department updated successfully.');
			header('Location: ?route=departments');
		} else {
			$errorMessage = trim((string) ($deptModel->getLastValidationError() ?? ''));
			flash_error($errorMessage !== '' ? $errorMessage : 'Failed to update department.');
			header('Location: ?route=departments/edit&id=' . $deptId);
		}
		exit;
	}
}
