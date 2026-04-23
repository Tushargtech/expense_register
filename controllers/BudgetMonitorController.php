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

	public function dispatchBudgetThresholdAlerts(array $rows): void
	{
		$budgetMonitorModel = new BudgetMonitorModel();
		$mailService = new MailService();

		foreach ($rows as $row) {
			$budgetId = (int) ($row['budget_id'] ?? 0);
			$allocated = (float) ($row['budget_allocated_amount'] ?? 0);
			$spent = (float) ($row['budget_spent_amount'] ?? 0);
			if ($budgetId <= 0 || $allocated <= 0) {
				continue;
			}

			$usagePercent = round(($spent / $allocated) * 100, 2);
			if ($usagePercent < 90.0) {
				continue;
			}

			if (!$budgetMonitorModel->shouldSendBudgetThresholdAlert($budgetId, $usagePercent, 90.0)) {
				continue;
			}

			$departmentId = (int) ($row['department_id'] ?? 0);
			$departmentName = trim((string) ($row['department_name'] ?? 'Department'));
			$budgetHead = trim((string) ($row['budget_category_name'] ?? ''));
			if ($budgetHead === '') {
				$budgetHead = trim((string) ($row['budget_category'] ?? 'Budget'));
			}
			$currency = trim((string) ($row['budget_currency'] ?? ''));
			$totalLimit = number_format($allocated, 2, '.', '');
			$usedAmount = number_format($spent, 2, '.', '');
			$recipients = $budgetMonitorModel->getBudgetThresholdRecipients($departmentId);

			foreach ($recipients as $recipient) {
				$recipientEmail = trim((string) ($recipient['email'] ?? ''));
				if ($recipientEmail === '') {
					continue;
				}

				$sent = $mailService->sendBudgetThresholdAlertEmail(
					$recipientEmail,
					$departmentName !== '' ? $departmentName : 'Department',
					$budgetHead !== '' ? $budgetHead : 'Budget',
					number_format($usagePercent, 1, '.', ''),
					$currency !== '' ? $currency : 'INR',
					$totalLimit,
					$usedAmount
				);

				if (!$sent) {
					error_log('Failed to send budget threshold alert for budget ' . $budgetId . ' to ' . $recipientEmail);
				}
			}
		}
	}

	public function dispatchBudgetThresholdAlertForBudgetId(int $budgetId): void
	{
		if ($budgetId <= 0) {
			return;
		}

		$budgetMonitorModel = new BudgetMonitorModel();
		$contextRow = $budgetMonitorModel->getBudgetThresholdAlertContextByBudgetId($budgetId);
		if (!is_array($contextRow)) {
			return;
		}

		$this->dispatchBudgetThresholdAlerts([$contextRow]);
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

		$perPage = 10;
		$currentPage = max(1, (int) ($_GET['page'] ?? 1));

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
		$this->dispatchBudgetThresholdAlerts($rows);
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
		$totalSummaryRows = count($departmentSummary);
		$totalPages = max(1, (int) ceil($totalSummaryRows / $perPage));
		if ($currentPage > $totalPages) {
			$currentPage = $totalPages;
		}
		$summaryOffset = ($currentPage - 1) * $perPage;
		$departmentSummary = array_slice($departmentSummary, $summaryOffset, $perPage);
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
