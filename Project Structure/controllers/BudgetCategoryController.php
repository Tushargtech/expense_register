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
			header('Location: ?route=module-1&error=unauthorized');
			exit;
		}

		$categoryModel = new BudgetCategoryModel();

		$filters = [
			'search' => trim((string) ($_GET['search'] ?? '')),
			'status' => trim((string) ($_GET['status'] ?? '')),
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
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/budget_category_list.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'budget-category-list';

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/budget_category_list.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}
}
