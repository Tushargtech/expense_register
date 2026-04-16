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

	private function summarizeByDepartmentAndCategory(array $rows): array
	{
		$summary = [];

		foreach ($rows as $row) {
			$department = trim((string) ($row['department_name'] ?? ''));
			if ($department === '') {
				$department = 'Unassigned';
			}

			$category = trim((string) ($row['budget_category_name'] ?? ''));
			if ($category === '') {
				$category = 'Unassigned';
			}

			$fiscalYear = trim((string) ($row['budget_fiscal_year'] ?? ''));
			if ($fiscalYear === '') {
				$fiscalYear = 'N/A';
			}

			$fiscalPeriod = trim((string) ($row['budget_fiscal_period'] ?? ''));
			if ($fiscalPeriod === '') {
				$fiscalPeriod = 'N/A';
			}

			$key = $department . '||' . $category . '||' . $fiscalYear . '||' . $fiscalPeriod;
			$allocated = (float) ($row['budget_allocated_amount'] ?? 0);
			$spentRaw = $row['budget_spent_amount'] ?? null;
			$spent = ($spentRaw === null || $spentRaw === '') ? null : (float) $spentRaw;

			if (!isset($summary[$key])) {
				$summary[$key] = [
					'department' => $department,
					'budget_category' => $category,
					'fiscal_year' => $fiscalYear,
					'fiscal_period' => $fiscalPeriod,
					'allocated' => 0.0,
					'spent' => 0.0,
					'has_spend_data' => false,
				];
			}

			$summary[$key]['allocated'] += $allocated;
			if ($spent !== null) {
				$summary[$key]['spent'] += $spent;
				$summary[$key]['has_spend_data'] = true;
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
		$managerDepartmentId = (int) ($auth['department_id'] ?? 0);
		$managerDepartmentName = (string) ($auth['department_name'] ?? '');

		$filters = [
			'department_id' => (int) ($_GET['department_id'] ?? 0),
			'fiscal_year' => trim((string) ($_GET['fiscal_year'] ?? '')),
		];

		// Finance department users can view all departments
		// Other users are restricted to their own department
		$scopeDepartmentId = null;
		$rbac = $this->rbac();
		if (!$rbac->canAccessBudgetMonitorForAllDepartments() && $managerDepartmentId > 0) {
			$scopeDepartmentId = $managerDepartmentId;
			$filters['department_id'] = $managerDepartmentId;
		}

		$budgetMonitorModel = new BudgetMonitorModel();
		$departmentModel = new DepartmentModel();
		$rows = $budgetMonitorModel->getMonitorRows($filters, $scopeDepartmentId !== null ? $scopeDepartmentId : ($filters['department_id'] > 0 ? $filters['department_id'] : null));
		$rowsForYearOptions = $budgetMonitorModel->getMonitorRows(
			['department_id' => $filters['department_id']],
			$scopeDepartmentId !== null ? $scopeDepartmentId : ($filters['department_id'] > 0 ? $filters['department_id'] : null)
		);
		$departments = $departmentModel->getAllDepartments();
		$departmentOptions = [];
		foreach ($departments as $department) {
			$departmentOptions[] = [
				'id' => (int) ($department['id'] ?? 0),
				'name' => (string) ($department['department_name'] ?? ''),
			];
		}

		$fiscalYears = [];
		$hasSpendData = false;
		$totalAllocated = 0.0;
		$totalSpent = 0.0;
		$totalRows = count($rows);

		foreach ($rowsForYearOptions as $row) {
			$year = trim((string) ($row['budget_fiscal_year'] ?? ''));
			if ($year !== '') {
				$fiscalYears[$year] = $year;
			}
		}

		foreach ($rows as $row) {

			$allocated = (float) ($row['budget_allocated_amount'] ?? 0);
			$totalAllocated += $allocated;

			$spentRaw = $row['budget_spent_amount'] ?? null;
			if ($spentRaw !== null && $spentRaw !== '') {
				$hasSpendData = true;
				$totalSpent += (float) $spentRaw;
			}
		}

		$departmentSummary = $this->summarizeByDepartmentAndCategory($rows);
		$fiscalYears = array_values($fiscalYears);
		sort($fiscalYears);

		$totals = [
			'allocated' => $totalAllocated,
			'spent' => $hasSpendData ? $totalSpent : null,
			'remaining' => $hasSpendData ? max(0, $totalAllocated - $totalSpent) : null,
			'utilization' => $hasSpendData && $totalAllocated > 0 ? round(($totalSpent / $totalAllocated) * 100, 1) : null,
		];

		$selectedDepartmentId = $scopeDepartmentId !== null ? $scopeDepartmentId : (int) ($filters['department_id'] ?? 0);
		$selectedFiscalYear = trim((string) ($filters['fiscal_year'] ?? ''));
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
		$pageStyles = ['assets/css/app.css'];
		$userName = (string) ($auth['name'] ?? 'User');
		$activeMenu = 'budget-monitor';
		$isFinanceRole = $this->isFinanceRole();
		$canViewAllDepartments = $this->rbac()->canAccessBudgetMonitorForAllDepartments();
		$canManageBudgetRecords = $this->rbac()->canManageBudgetRecords();
		$roleLabel = 'Budget Management';
		$scopeNote = '';

		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle,
			'pageStyles' => $pageStyles,
			'activeMenu' => $activeMenu,
		]);
		require ROOT_PATH . '/views/BudgetManagement/budget_monitor.php';
		renderAppLayoutEnd();
	}
}
