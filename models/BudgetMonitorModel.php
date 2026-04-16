<?php

class BudgetMonitorModel
{
	private PDO $db;
	private array $columnCache = [];

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

		$spentExpression = $spentColumn !== null ? 'db.`' . $spentColumn . '`' : 'NULL';
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
