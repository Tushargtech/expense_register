<?php

class BudgetMonitorController
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

	private function isAuthorized(): bool
	{
		return $this->rbac()->canAccessBudgetMonitor();
	}

	private function isFinanceRole(): bool
	{
		return $this->rbac()->canViewOrganizationBudgetUtilization();
	}

	private function formatMoney(?float $amount, string $currency = 'INR'): string
	{
		if ($amount === null) {
			return 'N/A';
		}

		return trim($currency) . ' ' . number_format($amount, 2);
	}

	private function summarizeByField(array $rows, string $fieldName, ?string $typeFieldName = null): array
	{
		$summary = [];

		foreach ($rows as $row) {
			$key = trim((string) ($row[$fieldName] ?? ''));
			if ($key === '') {
				$key = 'Unassigned';
			}

			$typeLabel = 'General';
			if ($typeFieldName !== null) {
				$typeLabel = trim((string) ($row[$typeFieldName] ?? ''));
				if ($typeLabel === '') {
					$typeLabel = 'General';
				}
			}

			$allocated = (float) ($row['budget_allocated_amount'] ?? 0);
			$spentRaw = $row['budget_spent_amount'] ?? null;
			$spent = ($spentRaw === null || $spentRaw === '') ? null : (float) $spentRaw;

			if (!isset($summary[$key])) {
				$summary[$key] = [
					'label' => $key,
					'type' => $typeLabel,
					'count' => 0,
					'allocated' => 0.0,
					'spent' => 0.0,
					'has_spend_data' => false,
				];
			} elseif ($typeFieldName !== null && ($summary[$key]['type'] ?? 'General') === 'General' && $typeLabel !== 'General') {
				$summary[$key]['type'] = $typeLabel;
			}

			$summary[$key]['count']++;
			$summary[$key]['allocated'] += $allocated;
			if ($spent !== null) {
				$summary[$key]['spent'] += $spent;
				$summary[$key]['has_spend_data'] = true;
			}
		}

		return array_values($summary);
	}

	private function buildTypeSummary(array $rows): array
	{
		$summary = [];

		foreach ($rows as $row) {
			$type = trim((string) ($row['budget_category_type'] ?? ''));
			if ($type === '') {
				$type = 'General';
			}

			$allocated = (float) ($row['budget_allocated_amount'] ?? 0);
			$spentRaw = $row['budget_spent_amount'] ?? null;
			$spent = ($spentRaw === null || $spentRaw === '') ? null : (float) $spentRaw;

			if (!isset($summary[$type])) {
				$summary[$type] = [
					'label' => $type,
					'count' => 0,
					'allocated' => 0.0,
					'spent' => 0.0,
					'has_spend_data' => false,
				];
			}

			$summary[$type]['count']++;
			$summary[$type]['allocated'] += $allocated;
			if ($spent !== null) {
				$summary[$type]['spent'] += $spent;
				$summary[$type]['has_spend_data'] = true;
			}
		}

		return array_values($summary);
	}

	public function index(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorized()) {
			header('Location: ?route=forbidden&code=rbac_budget_monitor');
			exit;
		}

		$auth = $_SESSION['auth'] ?? [];
		$role = strtolower(trim((string) ($auth['role'] ?? '')));
		$managerDepartmentId = (int) ($auth['department_id'] ?? 0);
		$managerDepartmentName = (string) ($auth['department_name'] ?? '');

		$filters = [
			'fiscal_year' => trim((string) ($_GET['fiscal_year'] ?? '')),
			'type' => trim((string) ($_GET['type'] ?? '')),
			'department_id' => (int) ($_GET['department_id'] ?? 0),
			'category_id' => (int) ($_GET['category_id'] ?? 0),
		];

		$scopeDepartmentId = null;
		if (!in_array($role, ['admin', 'finance'], true) && $managerDepartmentId > 0) {
			$scopeDepartmentId = $managerDepartmentId;
			$filters['department_id'] = $managerDepartmentId;
		}

		$budgetMonitorModel = new BudgetMonitorModel();
		$departmentModel = new DepartmentModel();
		$budgetCategoryModel = new BudgetCategoryModel();
		$rows = $budgetMonitorModel->getMonitorRows($filters, $scopeDepartmentId !== null ? $scopeDepartmentId : ($filters['department_id'] > 0 ? $filters['department_id'] : null));
		$departments = $departmentModel->getAllDepartments();
		$categories = $budgetCategoryModel->getAllCategories();
		$departmentOptions = [];
		$categoryOptions = [];
		foreach ($departments as $department) {
			$departmentOptions[] = [
				'id' => (int) ($department['id'] ?? 0),
				'name' => (string) ($department['department_name'] ?? ''),
			];
		}

		foreach ($categories as $category) {
			$categoryName = trim((string) ($category['budget_category_name'] ?? ''));
			if ($categoryName === '') {
				continue;
			}

			$categoryOptions[] = [
				'id' => (int) ($category['budget_category_id'] ?? 0),
				'name' => $categoryName,
			];
		}

		$fiscalYears = [];
		$hasSpendData = false;
		$totalAllocated = 0.0;
		$totalSpent = 0.0;
		$totalRows = count($rows);

		foreach ($rows as $row) {
			$year = trim((string) ($row['budget_fiscal_year'] ?? ''));
			if ($year !== '') {
				$fiscalYears[$year] = $year;
			}

			$allocated = (float) ($row['budget_allocated_amount'] ?? 0);
			$totalAllocated += $allocated;

			$spentRaw = $row['budget_spent_amount'] ?? null;
			if ($spentRaw !== null && $spentRaw !== '') {
				$hasSpendData = true;
				$totalSpent += (float) $spentRaw;
			}
		}

		$departmentSummary = $this->summarizeByField($rows, 'department_name');
		$categorySummary = $this->summarizeByField($rows, 'budget_category_name', 'budget_category_type');
		$typeSummary = $this->buildTypeSummary($rows);
		$fiscalYears = array_values($fiscalYears);
		sort($fiscalYears);

		$totals = [
			'allocated' => $totalAllocated,
			'spent' => $hasSpendData ? $totalSpent : null,
			'remaining' => $hasSpendData ? max(0, $totalAllocated - $totalSpent) : null,
			'utilization' => $hasSpendData && $totalAllocated > 0 ? round(($totalSpent / $totalAllocated) * 100, 1) : null,
		];

		$selectedDepartmentId = $scopeDepartmentId !== null ? $scopeDepartmentId : (int) ($filters['department_id'] ?? 0);
		$selectedCategoryId = (int) ($filters['category_id'] ?? 0);
		$selectedYear = (string) ($filters['fiscal_year'] ?? '');
		$selectedType = (string) ($filters['type'] ?? '');
		$selectedDepartmentName = '';
		if ($selectedDepartmentId > 0) {
			foreach ($departmentOptions as $departmentOption) {
				if ((int) $departmentOption['id'] === $selectedDepartmentId) {
					$selectedDepartmentName = (string) $departmentOption['name'];
					break;
				}
			}
		}

		$pageTitle = 'Budget Monitor / View - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/list.css', 'assets/css/budget-monitor.css'];
		$userName = (string) ($auth['name'] ?? 'User');
		$activeMenu = 'budget-monitor';
		$isFinanceRole = $this->isFinanceRole();
		$roleLabel = 'Budget Management';
		$scopeNote = '';

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/budget_monitor.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}
}
