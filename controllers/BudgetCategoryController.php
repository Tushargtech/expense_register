<?php

class BudgetCategoryController
{
	private function ensureAuthenticated(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			$_SESSION['auth_error'] = 'Please login to continue.';
			header('Location: ?route=dashboard');
			exit;
		}
	}

	private function isAuthorizedForBudgetCategoryAccess(): bool
	{
		$sessionRole = (string) ($_SESSION['auth']['role'] ?? $_SESSION['role'] ?? '');
		$normalizedRole = strtolower(trim($sessionRole));

		return in_array($normalizedRole, ['finance', 'admin'], true);
	}

	public function list(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetCategoryAccess()) {
			header('Location: ?route=home&error=unauthorized');
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
		$totalPages = max(1, (int) ceil($totalCategories / $perPage));

		if ($currentPage > $totalPages) {
			$currentPage = $totalPages;
		}

		$offset = ($currentPage - 1) * $perPage;
		$categories = $categoryModel->getFilteredCategories($filters, $perPage, $offset);

		$pageTitle = 'Budget Category Management - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/list.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'budget-category-list';

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/budget_category_list.php';
		require ROOT_PATH . '/views/templates/footer.php';
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
		$allowedTypes = ['expense', 'purchase'];

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

		if (!$this->isAuthorizedForBudgetCategoryAccess()) {
			header('Location: ?route=home&error=unauthorized');
			exit;
		}

		$pageTitle = 'Create Budget Category - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/creation.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'budget-category-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = false;
		$formAction = '?route=budget-categories/create';
		$formTitle = 'Create Budget Category';
		$submitLabel = 'Create Category';
		$category = [
			'budget_category_id' => 0,
			'budget_category_name' => '',
			'budget_category_code' => '',
			'budget_category_type' => '',
			'budget_category_description' => '',
			'budget_category_is_active' => 1,
		];

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/budget_category_creation.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}

	public function edit(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetCategoryAccess()) {
			header('Location: ?route=home&error=unauthorized');
			exit;
		}

		$categoryId = (int) ($_GET['id'] ?? 0);
		if ($categoryId <= 0) {
			header('Location: ?route=budget-categories&error=' . urlencode('Invalid budget category id'));
			exit;
		}

		$categoryModel = new BudgetCategoryModel();
		$category = $categoryModel->getCategoryById($categoryId);

		if ($category === null) {
			header('Location: ?route=budget-categories&error=' . urlencode('Budget category not found'));
			exit;
		}

		$pageTitle = 'Edit Budget Category - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/creation.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'budget-category-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = true;
		$formAction = '?route=budget-categories/edit&id=' . $categoryId;
		$formTitle = 'Edit Budget Category';
		$submitLabel = 'Update Category';

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/budget_category_creation.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}

	public function store(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetCategoryAccess()) {
			header('Location: ?route=home&error=unauthorized');
			exit;
		}

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=budget-categories/create');
			exit;
		}

		$categoryData = $this->normalizeCategoryPayload($_POST);

		if (!$this->isValidCategoryPayload($categoryData)) {
			header('Location: ?route=budget-categories/create&error=' . urlencode('Please fill all required fields correctly.'));
			exit;
		}

		$categoryModel = new BudgetCategoryModel();
		$success = $categoryModel->createCategory($categoryData);

		if ($success) {
			header('Location: ?route=budget-categories&success=' . urlencode('Budget category created successfully.'));
		} else {
			header('Location: ?route=budget-categories/create&error=' . urlencode('Failed to create budget category.'));
		}
		exit;
	}

	public function update(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetCategoryAccess()) {
			header('Location: ?route=home&error=unauthorized');
			exit;
		}

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=budget-categories');
			exit;
		}

		$categoryId = (int) ($_GET['id'] ?? 0);
		if ($categoryId <= 0) {
			header('Location: ?route=budget-categories&error=' . urlencode('Invalid budget category id'));
			exit;
		}

		$categoryData = $this->normalizeCategoryPayload($_POST);

		if (!$this->isValidCategoryPayload($categoryData)) {
			header('Location: ?route=budget-categories/edit&id=' . $categoryId . '&error=' . urlencode('Please fill all required fields correctly.'));
			exit;
		}

		$categoryModel = new BudgetCategoryModel();
		$success = $categoryModel->updateCategory($categoryId, $categoryData);

		if ($success) {
			header('Location: ?route=budget-categories&success=' . urlencode('Budget category updated successfully.'));
		} else {
			header('Location: ?route=budget-categories/edit&id=' . $categoryId . '&error=' . urlencode('Failed to update budget category.'));
		}
		exit;
	}
}
