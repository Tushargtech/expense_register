<?php

class BudgetCategoryController
{
	private function rbac(): RbacService
	{
		return new RbacService();
	}

	private function ensureAuthenticated(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			flash_error('Please login to continue.');
			header('Location: ?route=dashboard');
			exit;
		}
	}

	private function isAuthorizedForBudgetCategoryAccess(): bool
	{
		return $this->rbac()->canViewBudgetCategories();
	}

	private function isAuthorizedForBudgetCategoryManage(): bool
	{
		return $this->rbac()->canManageBudgetCategories();
	}

	public function list(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetCategoryAccess()) {
			header('Location: ?route=forbidden&code=rbac_budget_category');
			exit;
		}

		$categoryModel = new BudgetCategoryModel();

		$filters = [
			'search' => trim((string) ($_GET['search'] ?? '')),
			'status' => trim((string) ($_GET['status'] ?? '')),
			'type' => trim((string) ($_GET['type'] ?? '')),
		];

		$perPage = 10;
		$currentPage = max(1, (int) ($_GET['page'] ?? 1));
		$totalCategories = $categoryModel->countFilteredCategories($filters);
		if (!empty($_GET['download'])) {
			$exportRows = $categoryModel->getFilteredCategories($filters, max(1, $totalCategories), 0);
			$exportData = [];
			foreach ($exportRows as $category) {
				$exportData[] = [
					(string) ($category['budget_category_code'] ?? ''),
					(string) ($category['budget_category_name'] ?? ''),
					ucfirst(strtolower((string) ($category['budget_category_type'] ?? ''))),
					((int) ($category['budget_category_is_active'] ?? 0) === 1) ? 'Active' : 'Inactive',
				];
			}

			$exportService = new SpreadsheetExportService();
			$exportService->streamXlsx(
				'budget-categories-' . date('YmdHis') . '.xlsx',
				['Category Code', 'Category Name', 'Category Type', 'Status'],
				$exportData,
				'Budget Categories'
			);
		}
		$totalPages = max(1, (int) ceil($totalCategories / $perPage));

		if ($currentPage > $totalPages) {
			$currentPage = $totalPages;
		}

		$offset = ($currentPage - 1) * $perPage;
		$categories = $categoryModel->getFilteredCategories($filters, $perPage, $offset);

		$pageTitle = 'Budget Category Management - Expense Register';
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'budget-category-list';
		$canManageBudgetCategories = $this->rbac()->canManageBudgetCategories();
		$lookupModel = new LookupModel();
		$categoryTypeOptions = $lookupModel->getBudgetCategoryTypes();

		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle,
			'pageStyles' => $pageStyles,
			'activeMenu' => $activeMenu,
		]);
		require ROOT_PATH . '/views/BudgetManagement/budget_category_list.php';
		renderAppLayoutEnd();
	}

	private function normalizeCategoryPayload(array $source): array
	{
		$categoryType = strtolower(trim((string) ($source['budget_category_type'] ?? '')));

		return [
			'budget_category_name' => trim((string) ($source['budget_category_name'] ?? '')),
			'budget_category_code' => trim((string) ($source['budget_category_code'] ?? '')),
			'budget_category_type' => $categoryType,
			'budget_category_description' => trim((string) ($source['budget_category_description'] ?? '')),
			'budget_category_is_active' => (int) ($source['budget_category_is_active'] ?? 1),
			'budget_category_created_by' => (int) ($_SESSION['auth']['user_id'] ?? 0),
		];
	}

	private function isValidCategoryPayload(array $categoryData): bool
	{
		$lookupModel = new LookupModel();
		$allowedTypes = $lookupModel->getBudgetCategoryTypes();
		if ($allowedTypes === [] && $categoryData['budget_category_type'] !== '') {
			$allowedTypes = [$categoryData['budget_category_type']];
		}

		return (
			$categoryData['budget_category_name'] !== '' &&
			$categoryData['budget_category_code'] !== '' &&
			in_array($categoryData['budget_category_type'], $allowedTypes, true) &&
			in_array((int) $categoryData['budget_category_is_active'], [0, 1], true)
		);
	}

	public function create(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetCategoryManage()) {
			header('Location: ?route=forbidden&code=rbac_budget_category');
			exit;
		}

		$pageTitle = 'Create Budget Category - Expense Register';
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'budget-category-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = false;
		$formAction = buildCleanRouteUrl('budget-categories/create');
		$formTitle = 'Create Budget Category';
		$submitLabel = 'Create Category';
		$lookupModel = new LookupModel();
		$categoryTypeOptions = $lookupModel->getBudgetCategoryTypes();
		$category = [
			'budget_category_id' => 0,
			'budget_category_name' => '',
			'budget_category_code' => '',
			'budget_category_type' => '',
			'budget_category_description' => '',
			'budget_category_is_active' => 1,
		];

		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle,
			'pageStyles' => $pageStyles,
			'activeMenu' => $activeMenu,
		]);
		require ROOT_PATH . '/views/BudgetManagement/budget_category_creation.php';
		renderAppLayoutEnd();
	}

	public function edit(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetCategoryManage()) {
			header('Location: ?route=forbidden&code=rbac_budget_category');
			exit;
		}

		$categoryId = (int) ($_GET['id'] ?? 0);
		if ($categoryId <= 0) {
			flash_error('Invalid budget category id');
			header('Location: ?route=budget-categories');
			exit;
		}

		$categoryModel = new BudgetCategoryModel();
		$category = $categoryModel->getCategoryById($categoryId);

		if ($category === null) {
			flash_error('Budget category not found');
			header('Location: ?route=budget-categories');
			exit;
		}

		$pageTitle = 'Edit Budget Category - Expense Register';
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'budget-category-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = true;
		$formAction = buildCleanRouteUrl('budget-categories/edit', ['id' => $categoryId]);
		$formTitle = 'Edit Budget Category';
		$submitLabel = 'Update Category';
		$lookupModel = new LookupModel();
		$categoryTypeOptions = $lookupModel->getBudgetCategoryTypes();

		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle,
			'pageStyles' => $pageStyles,
			'activeMenu' => $activeMenu,
		]);
		require ROOT_PATH . '/views/BudgetManagement/budget_category_creation.php';
		renderAppLayoutEnd();
	}

	public function store(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetCategoryManage()) {
			header('Location: ?route=forbidden&code=rbac_budget_category');
			exit;
		}

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ' . buildCleanRouteUrl('budget-categories/create'));
			exit;
		}

		$categoryData = $this->normalizeCategoryPayload($_POST);

		if (!$this->isValidCategoryPayload($categoryData)) {
			flash_error('Please fill all required fields correctly.');
			header('Location: ' . buildCleanRouteUrl('budget-categories/create'));
			exit;
		}

		$categoryModel = new BudgetCategoryModel();
		$success = $categoryModel->createCategory($categoryData);

		if ($success) {
			RbacService::audit('budget_category_create', ['code' => $categoryData['budget_category_code']]);
			flash_success('Budget category created successfully.');
			header('Location: ' . buildCleanRouteUrl('budget-categories'));
		} else {
			flash_error('Failed to create budget category.');
			header('Location: ' . buildCleanRouteUrl('budget-categories/create'));
		}
		exit;
	}

	public function update(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetCategoryManage()) {
			header('Location: ?route=forbidden&code=rbac_budget_category');
			exit;
		}

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ' . buildCleanRouteUrl('budget-categories'));
			exit;
		}

		$categoryId = (int) ($_GET['id'] ?? 0);
		if ($categoryId <= 0) {
			flash_error('Invalid budget category id');
			header('Location: ' . buildCleanRouteUrl('budget-categories'));
			exit;
		}

		$categoryData = $this->normalizeCategoryPayload($_POST);

		if (!$this->isValidCategoryPayload($categoryData)) {
			flash_error('Please fill all required fields correctly.');
			header('Location: ' . buildCleanRouteUrl('budget-categories/edit', ['id' => $categoryId]));
			exit;
		}

		$categoryModel = new BudgetCategoryModel();
		$success = $categoryModel->updateCategory($categoryId, $categoryData);

		if ($success) {
			RbacService::audit('budget_category_update', ['budget_category_id' => $categoryId]);
			flash_success('Budget category updated successfully.');
			header('Location: ' . buildCleanRouteUrl('budget-categories'));
		} else {
			flash_error('Failed to update budget category.');
			header('Location: ' . buildCleanRouteUrl('budget-categories/edit', ['id' => $categoryId]));
		}
		exit;
	}
}
