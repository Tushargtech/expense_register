<?php

class BudgetMonitorModel
{
	private PDO $db;
	private array $columnCache = [];
	private ?bool $thresholdAlertStateTableReady = null;

	public function __construct()
	{
		$this->db = getDB();
	}

	private function getTableColumns(string $tableName): array
	{
		if (isset($this->columnCache[$tableName])) {
			return $this->columnCache[$tableName];
		}

		try {
			$statement = $this->db->query('SHOW COLUMNS FROM ' . $tableName);
			$rows = $statement->fetchAll(PDO::FETCH_ASSOC);
		} catch (Throwable $error) {
			$this->columnCache[$tableName] = [];
			return $this->columnCache[$tableName];
		}

		$columns = [];
		foreach ($rows as $row) {
			$columnName = (string) ($row['Field'] ?? '');
			if ($columnName !== '') {
				$columns[] = $columnName;
			}
		}

		$this->columnCache[$tableName] = $columns;

		return $columns;
	}

	private function pickColumn(array $columns, array $candidates): ?string
	{
		foreach ($candidates as $candidate) {
			if (in_array($candidate, $columns, true)) {
				return $candidate;
			}
		}

		return null;
	}

	private function ensureThresholdAlertStateTable(): bool
	{
		if ($this->thresholdAlertStateTableReady !== null) {
			return $this->thresholdAlertStateTableReady;
		}

		try {
			$this->db->exec(
				"CREATE TABLE IF NOT EXISTS budget_threshold_alert_states (
					budget_id INT NOT NULL,
					threshold_percent DECIMAL(5,2) NOT NULL DEFAULT 90.00,
					last_usage_percent DECIMAL(7,2) NOT NULL DEFAULT 0.00,
					last_alert_sent_at TIMESTAMP NULL DEFAULT NULL,
					last_evaluated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					PRIMARY KEY (budget_id)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
			);

			$this->thresholdAlertStateTableReady = true;
			return true;
		} catch (Throwable $error) {
			$this->thresholdAlertStateTableReady = false;
			error_log('BudgetMonitorModel::ensureThresholdAlertStateTable failed: ' . $error->getMessage());
			return false;
		}
	}

	public function shouldSendBudgetThresholdAlert(int $budgetId, float $usagePercent, float $thresholdPercent = 90.0): bool
	{
		if ($budgetId <= 0 || !$this->ensureThresholdAlertStateTable()) {
			return false;
		}

		$lastUsagePercent = null;
		$stmt = $this->db->prepare('SELECT last_usage_percent FROM budget_threshold_alert_states WHERE budget_id = :budget_id LIMIT 1');
		$stmt->execute([':budget_id' => $budgetId]);
		$existing = $stmt->fetch(PDO::FETCH_ASSOC);
		if (is_array($existing) && array_key_exists('last_usage_percent', $existing)) {
			$lastUsagePercent = (float) $existing['last_usage_percent'];
		}

		$shouldSend = $usagePercent >= $thresholdPercent && ($lastUsagePercent === null || $lastUsagePercent < $thresholdPercent);

		$upsert = $this->db->prepare(
			'INSERT INTO budget_threshold_alert_states (budget_id, threshold_percent, last_usage_percent, last_alert_sent_at)
			 VALUES (:budget_id, :threshold_percent, :last_usage_percent, :last_alert_sent_at)
			 ON DUPLICATE KEY UPDATE
			 	threshold_percent = VALUES(threshold_percent),
			 	last_usage_percent = VALUES(last_usage_percent),
			 	last_alert_sent_at = VALUES(last_alert_sent_at),
			 	last_evaluated_at = CURRENT_TIMESTAMP'
		);
		$upsert->execute([
			':budget_id' => $budgetId,
			':threshold_percent' => number_format($thresholdPercent, 2, '.', ''),
			':last_usage_percent' => number_format($usagePercent, 2, '.', ''),
			':last_alert_sent_at' => $shouldSend ? date('Y-m-d H:i:s') : null,
		]);

		return $shouldSend;
	}

	public function getBudgetThresholdRecipients(int $departmentId): array
	{
		$recipients = [];

		try {
			$financeStmt = $this->db->prepare(
				"SELECT user_name, user_email
				 FROM users
				 WHERE user_is_active = 1
				   AND LOWER(TRIM(user_role)) = 'finance'
				 ORDER BY user_name ASC"
			);
			$financeStmt->execute();
			foreach ($financeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$email = strtolower(trim((string) ($row['user_email'] ?? '')));
				if ($email === '') {
					continue;
				}

				$recipients[$email] = [
					'name' => trim((string) ($row['user_name'] ?? 'Finance Team Member')),
					'email' => trim((string) ($row['user_email'] ?? '')),
					'role' => 'finance',
				];
			}

			if ($departmentId > 0) {
				$deptStmt = $this->db->prepare(
					"SELECT d.department_name, u.user_name, u.user_email
					 FROM departments d
					 LEFT JOIN users u ON u.user_id = d.department_head_user_id
					 WHERE d.id = :department_id
					 LIMIT 1"
				);
				$deptStmt->execute([':department_id' => $departmentId]);
				$department = $deptStmt->fetch(PDO::FETCH_ASSOC);
				if (is_array($department)) {
					$email = strtolower(trim((string) ($department['user_email'] ?? '')));
					if ($email !== '') {
						$recipients[$email] = [
							'name' => trim((string) ($department['user_name'] ?? 'Department Head')),
							'email' => trim((string) ($department['user_email'] ?? '')),
							'role' => 'department_head',
							'department_name' => trim((string) ($department['department_name'] ?? '')),
						];
					}
				}
			}
		} catch (Throwable $error) {
			error_log('BudgetMonitorModel::getBudgetThresholdRecipients failed: ' . $error->getMessage());
		}

		return array_values($recipients);
	}

	public function getBudgetThresholdAlertContextByBudgetId(int $budgetId): ?array
	{
		if ($budgetId <= 0) {
			return null;
		}

		$spentColumn = $this->pickColumn($this->getTableColumns('department_budgets'), [
			'budget_spent_amount',
			'budget_utilized_amount',
			'budget_consumed_amount',
			'spent_amount',
			'actual_spend',
			'budget_spent',
		]);

		$legacySpentExpression = $spentColumn !== null ? 'db.`' . $spentColumn . '`' : 'NULL';
		$spentExpression = 'COALESCE(req_spend.approved_spend_amount, ' . $legacySpentExpression . ', 0)';

		$sql = "SELECT
				db.budget_id,
				db.department_id,
				COALESCE(d.department_name, CONCAT('Department #', db.department_id)) AS department_name,
				db.budget_fiscal_year,
				db.budget_fiscal_period,
				db.budget_category_id,
				COALESCE(bc.budget_category_name, db.budget_category, CONCAT('Category #', db.budget_category_id)) AS budget_category_name,
				db.budget_currency,
				db.budget_allocated_amount,
				{$spentExpression} AS budget_spent_amount,
				db.budget_notes
			FROM department_budgets db
			LEFT JOIN departments d ON d.id = db.department_id
			LEFT JOIN budget_categories bc ON bc.budget_category_id = db.budget_category_id
			LEFT JOIN (
				SELECT
					r.department_id,
					r.budget_category_id,
					DATE_FORMAT(r.request_submitted_at, '%Y') AS approved_fiscal_year,
					CONCAT('Q', QUARTER(r.request_submitted_at)) AS approved_fiscal_period,
					SUM(r.request_amount) AS approved_spend_amount
				FROM requests r
				WHERE r.request_status = 'approved'
				GROUP BY
					r.department_id,
					r.budget_category_id,
					DATE_FORMAT(r.request_submitted_at, '%Y'),
					CONCAT('Q', QUARTER(r.request_submitted_at))
			) req_spend ON req_spend.department_id = db.department_id
					   AND req_spend.budget_category_id = db.budget_category_id
					   AND LOWER(TRIM(req_spend.approved_fiscal_year)) = LOWER(TRIM(db.budget_fiscal_year))
					   AND LOWER(TRIM(req_spend.approved_fiscal_period)) = LOWER(TRIM(db.budget_fiscal_period))
			WHERE db.budget_id = :budget_id
			LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([':budget_id' => $budgetId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row !== false ? $row : null;
	}

	public function getMonitorRows(array $filters = [], ?int $departmentId = null): array
	{
		$budgetColumns = $this->getTableColumns('department_budgets');
		$departmentColumns = $this->getTableColumns('departments');
		$categoryColumns = $this->getTableColumns('budget_categories');

		$spentColumn = $this->pickColumn($budgetColumns, [
			'budget_spent_amount',
			'budget_utilized_amount',
			'budget_consumed_amount',
			'spent_amount',
			'actual_spend',
			'budget_spent',
		]);

		$categoryNameColumn = $this->pickColumn($categoryColumns, [
			'budget_category_name',
			'budget_category',
		]);
		$categoryTypeColumn = $this->pickColumn($categoryColumns, [
			'budget_category_type',
		]);

		$legacySpentExpression = $spentColumn !== null ? 'db.`' . $spentColumn . '`' : 'NULL';
		$spentExpression = 'COALESCE(req_spend.approved_spend_amount, ' . $legacySpentExpression . ', 0)';
		$categoryNameExpression = $categoryNameColumn !== null ? 'bc.`' . $categoryNameColumn . '`' : 'db.`budget_category`';
		$categoryTypeExpression = $categoryTypeColumn !== null ? 'bc.`' . $categoryTypeColumn . '`' : 'NULL';
		$departmentNameExpression = in_array('department_name', $departmentColumns, true)
			? 'COALESCE(d.department_name, CONCAT("Department #", db.department_id))'
			: 'CONCAT("Department #", db.department_id)';

		$sql = "SELECT
				db.budget_id,
				db.department_id,
				{$departmentNameExpression} AS department_name,
				db.budget_fiscal_year,
				db.budget_fiscal_period,
				db.budget_category_id,
				COALESCE({$categoryNameExpression}, db.budget_category, CONCAT('Category #', db.budget_category_id)) AS budget_category_name,
				{$categoryTypeExpression} AS budget_category_type,
				db.budget_allocated_amount,
				{$spentExpression} AS budget_spent_amount,
				db.budget_currency,
				db.budget_notes,
				db.budget_uploaded_by,
				db.budget_created_at
			FROM department_budgets db
			LEFT JOIN departments d ON d.id = db.department_id
			LEFT JOIN budget_categories bc ON bc.budget_category_id = db.budget_category_id
			LEFT JOIN (
				SELECT
					r.department_id,
					r.budget_category_id,
					DATE_FORMAT(r.request_submitted_at, '%Y') AS approved_fiscal_year,
					CONCAT('Q', QUARTER(r.request_submitted_at)) AS approved_fiscal_period,
					SUM(r.request_amount) AS approved_spend_amount
				FROM requests r
				WHERE r.request_status = 'approved'
				GROUP BY
					r.department_id,
					r.budget_category_id,
					DATE_FORMAT(r.request_submitted_at, '%Y'),
					CONCAT('Q', QUARTER(r.request_submitted_at))
			) req_spend ON req_spend.department_id = db.department_id
					   AND req_spend.budget_category_id = db.budget_category_id
					   AND LOWER(TRIM(req_spend.approved_fiscal_year)) = LOWER(TRIM(db.budget_fiscal_year))
					   AND LOWER(TRIM(req_spend.approved_fiscal_period)) = LOWER(TRIM(db.budget_fiscal_period))
			WHERE 1 = 1";

		$params = [];

		if ($departmentId !== null && $departmentId > 0) {
			$sql .= ' AND db.department_id = :department_id';
			$params[':department_id'] = $departmentId;
		}

		$fiscalYear = trim((string) ($filters['fiscal_year'] ?? ''));
		if ($fiscalYear !== '') {
			$sql .= ' AND db.budget_fiscal_year = :fiscal_year';
			$params[':fiscal_year'] = $fiscalYear;
		}

		$type = strtolower(trim((string) ($filters['type'] ?? '')));
		if ($type !== '' && $categoryTypeColumn !== null) {
			$sql .= ' AND LOWER(TRIM(bc.`' . $categoryTypeColumn . '`)) = :type';
			$params[':type'] = $type;
		}

		$categoryId = (int) ($filters['category_id'] ?? 0);
		if ($categoryId > 0) {
			$sql .= ' AND db.budget_category_id = :category_id';
			$params[':category_id'] = $categoryId;
		}

		$sql .= ' ORDER BY department_name ASC, db.budget_fiscal_year DESC, db.budget_fiscal_period ASC, budget_category_name ASC';

		$statement = $this->db->prepare($sql);
		foreach ($params as $placeholder => $value) {
			if ($placeholder === ':department_id' || $placeholder === ':category_id') {
				$statement->bindValue($placeholder, (int) $value, PDO::PARAM_INT);
				continue;
			}

			$statement->bindValue($placeholder, (string) $value, PDO::PARAM_STR);
		}
		$statement->execute();

		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}
}
