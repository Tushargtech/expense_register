<?php

class BudgetModel
{
	private PDO $db;
	private ?array $budgetTableColumns = null;
	private ?array $budgetCategoryTableColumns = null;
	private ?string $lastInsertError = null;

	public function __construct()
	{
		$this->db = getDB();
	}

	private function getCurrentIstDateTime(): string
	{
		$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

		return $istNow->format('Y-m-d H:i:s');
	}

	private function getBudgetTableColumns(): array
	{
		if ($this->budgetTableColumns !== null) {
			return $this->budgetTableColumns;
		}

		$stmt = $this->db->query('SHOW COLUMNS FROM department_budgets');
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$this->budgetTableColumns = [];

		foreach ($rows as $row) {
			$columnName = (string) ($row['Field'] ?? '');
			if ($columnName !== '') {
				$this->budgetTableColumns[] = $columnName;
			}
		}

		return $this->budgetTableColumns;
	}

	private function getBudgetCategoryTableColumns(): array
	{
		if ($this->budgetCategoryTableColumns !== null) {
			return $this->budgetCategoryTableColumns;
		}

		$stmt = $this->db->query('SHOW COLUMNS FROM budget_categories');
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$this->budgetCategoryTableColumns = [];

		foreach ($rows as $row) {
			$columnName = (string) ($row['Field'] ?? '');
			if ($columnName !== '') {
				$this->budgetCategoryTableColumns[] = $columnName;
			}
		}

		return $this->budgetCategoryTableColumns;
	}

	public function resolveDepartment(?string $departmentReference): array
	{
		$reference = trim((string) $departmentReference);
		if ($reference === '') {
			return [
				'department_id' => null,
				'department_name' => null,
			];
		}

		if (ctype_digit($reference)) {
			$stmt = $this->db->prepare('SELECT id, department_name FROM departments WHERE id = :id LIMIT 1');
			$stmt->execute([':id' => (int) $reference]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($row !== false) {
				return [
					'department_id' => (int) ($row['id'] ?? 0),
					'department_name' => (string) ($row['department_name'] ?? $reference),
				];
			}
		}

		$stmt = $this->db->prepare(
			'SELECT id, department_name
			 FROM departments
			 WHERE department_code = :exact
				OR department_name = :exact
				OR LOWER(TRIM(department_name)) = LOWER(TRIM(:exact))
			 LIMIT 1'
		);
		$stmt->execute([':exact' => $reference]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($row === false) {
			return [
				'department_id' => null,
				'department_name' => null,
			];
		}

		return [
			'department_id' => (int) ($row['id'] ?? 0),
			'department_name' => (string) ($row['department_name'] ?? $reference),
		];
	}

	public function resolveDepartmentId(?string $departmentReference): ?int
	{
		$department = $this->resolveDepartment($departmentReference);

		return $department['department_id'] !== null ? (int) $department['department_id'] : null;
	}

	public function resolveBudgetCategory(?string $categoryReference, ?string $categoryType = null): array
	{
		$reference = trim((string) $categoryReference);
		$type = strtolower(trim((string) $categoryType));
		$categoryColumns = $this->getBudgetCategoryTableColumns();
		$nameColumn = in_array('budget_category_name', $categoryColumns, true) ? 'budget_category_name' : 'budget_category';
		$codeColumn = in_array('budget_category_code', $categoryColumns, true) ? 'budget_category_code' : null;
		$typeColumn = in_array('budget_category_type', $categoryColumns, true) ? 'budget_category_type' : null;

		if ($reference === '') {
			return [
				'budget_category_id' => null,
				'budget_category' => null,
			];
		}

		if (ctype_digit($reference)) {
			$stmt = $this->db->prepare(
				'SELECT budget_category_id, ' . $nameColumn . ' AS category_name
				 FROM budget_categories
				 WHERE budget_category_id = :id
				 LIMIT 1'
			);
			$stmt->execute([':id' => (int) $reference]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($row !== false) {
				return [
					'budget_category_id' => (int) ($row['budget_category_id'] ?? 0),
					'budget_category' => (string) ($row['category_name'] ?? $reference),
				];
			}
		}

		$conditions = [
			$nameColumn . ' = :reference',
			'LOWER(TRIM(' . $nameColumn . ')) = LOWER(TRIM(:reference))',
		];

		if ($codeColumn !== null) {
			$conditions[] = $codeColumn . ' = :reference';
		}

		$sql =
			'SELECT budget_category_id, ' . $nameColumn . ' AS category_name
			 FROM budget_categories
			 WHERE (' . implode(' OR ', $conditions) . ')';

		$params = [':reference' => $reference];
		if ($type !== '' && $typeColumn !== null) {
			$sql .= ' AND LOWER(TRIM(' . $typeColumn . ')) = :type';
			$params[':type'] = $type;
		}

		$sql .= ' LIMIT 1';
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($row !== false) {
			return [
				'budget_category_id' => (int) ($row['budget_category_id'] ?? 0),
				'budget_category' => (string) ($row['category_name'] ?? $reference),
			];
		}

		return [
			'budget_category_id' => null,
			'budget_category' => $reference,
		];
	}

	public function insertExtractedData(array $data): bool
	{
		$this->lastInsertError = null;
		$columns = $this->getBudgetTableColumns();

		$insertData = [
			'department_id' => $data['department_id'] ?? null,
			'budget_fiscal_year' => $data['budget_fiscal_year'] ?? null,
			'budget_fiscal_period' => $data['budget_fiscal_period'] ?? null,
			'budget_category' => $data['budget_category'] ?? null,
			'budget_category_id' => $data['budget_category_id'] ?? null,
			'budget_allocated_amount' => $data['budget_allocated_amount'] ?? null,
			'budget_currency' => $data['budget_currency'] ?? null,
			'budget_notes' => $data['budget_notes'] ?? null,
			'budget_uploaded_by' => $data['budget_uploaded_by'] ?? null,
		];

		if (in_array('budget_created_at', $columns, true)) {
			$insertData['budget_created_at'] = $this->getCurrentIstDateTime();
		}

		$requiredFields = ['department_id', 'budget_fiscal_year', 'budget_fiscal_period', 'budget_allocated_amount'];
		foreach ($requiredFields as $field) {
			if (!array_key_exists($field, $insertData) || $insertData[$field] === null || $insertData[$field] === '') {
				$this->lastInsertError = 'Missing required field: ' . $field;
				return false;
			}
		}

		$sqlColumns = [];
		$placeholders = [];
		$params = [];

		foreach ($insertData as $columnName => $value) {
			if (!in_array($columnName, $columns, true)) {
				continue;
			}

			$placeholder = ':' . $columnName;
			$sqlColumns[] = $columnName;
			$placeholders[] = $placeholder;
			$params[$placeholder] = $value;
		}

		if (empty($sqlColumns)) {
			$this->lastInsertError = 'No valid insert columns resolved for department_budgets table.';
			return false;
		}

		$sql =
			'INSERT INTO department_budgets (' . implode(', ', $sqlColumns) . ') ' .
			'VALUES (' . implode(', ', $placeholders) . ')';

		$stmt = $this->db->prepare($sql);

		foreach ($params as $placeholder => $value) {
			if ($value === null) {
				$stmt->bindValue($placeholder, null, PDO::PARAM_NULL);
			} elseif (is_int($value)) {
				$stmt->bindValue($placeholder, $value, PDO::PARAM_INT);
			} else {
				$stmt->bindValue($placeholder, (string) $value, PDO::PARAM_STR);
			}
		}

		try {
			return $stmt->execute();
		} catch (Throwable $error) {
			if ($error instanceof PDOException) {
				$pdoMessage = '';
				if (isset($error->errorInfo[2]) && is_string($error->errorInfo[2])) {
					$pdoMessage = trim($error->errorInfo[2]);
				}
				$this->lastInsertError = $pdoMessage !== '' ? $pdoMessage : $error->getMessage();
			} else {
				$this->lastInsertError = $error->getMessage();
			}
			return false;
		}
	}

	public function getLastInsertError(): ?string
	{
		return $this->lastInsertError;
	}

	public function findExistingBudgetByScope(int $departmentId, int $budgetCategoryId, string $fiscalYear, string $fiscalPeriod): ?array
	{
		if ($departmentId <= 0 || $budgetCategoryId <= 0 || trim($fiscalYear) === '' || trim($fiscalPeriod) === '') {
			return null;
		}

		$sql = 'SELECT
				budget_id,
				department_id,
				budget_category_id,
				budget_fiscal_year,
				budget_fiscal_period,
				budget_allocated_amount,
				budget_currency,
				budget_notes,
				budget_created_at,
				budget_updated_at
			FROM department_budgets
			WHERE department_id = :department_id
			  AND budget_category_id = :budget_category_id
			  AND LOWER(TRIM(budget_fiscal_year)) = LOWER(TRIM(:budget_fiscal_year))
			  AND LOWER(TRIM(budget_fiscal_period)) = LOWER(TRIM(:budget_fiscal_period))
			ORDER BY budget_id DESC
			LIMIT 1';

		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':department_id', $departmentId, PDO::PARAM_INT);
		$stmt->bindValue(':budget_category_id', $budgetCategoryId, PDO::PARAM_INT);
		$stmt->bindValue(':budget_fiscal_year', trim($fiscalYear), PDO::PARAM_STR);
		$stmt->bindValue(':budget_fiscal_period', trim($fiscalPeriod), PDO::PARAM_STR);
		$stmt->execute();

		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row !== false ? $row : null;
	}

	public function getBudgetById(int $budgetId): ?array
	{
		$sql = 'SELECT
				db.budget_id,
				db.department_id,
				d.department_name,
				db.budget_fiscal_year,
				db.budget_fiscal_period,
				db.budget_category,
				db.budget_category_id,
				db.budget_allocated_amount,
				db.budget_currency,
				db.budget_notes,
				db.budget_uploaded_by,
				db.budget_created_at,
				bc.budget_category_name,
				bc.budget_category_type,
				u.user_name AS uploaded_by_name
			FROM department_budgets db
			LEFT JOIN departments d ON d.id = db.department_id
			LEFT JOIN budget_categories bc ON bc.budget_category_id = db.budget_category_id
			LEFT JOIN users u ON u.user_id = db.budget_uploaded_by
			WHERE db.budget_id = :budget_id
			LIMIT 1';

		$stmt = $this->db->prepare($sql);
		$stmt->execute([':budget_id' => $budgetId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row !== false ? $row : null;
	}

	public function updateBudget(int $budgetId, array $budgetData): bool
	{
		$columns = $this->getBudgetTableColumns();
		$setClauses = [];
		$params = [':budget_id' => $budgetId];

		$updatableColumns = [
			'department_id' => (int) ($budgetData['department_id'] ?? 0),
			'budget_fiscal_year' => (string) ($budgetData['budget_fiscal_year'] ?? ''),
			'budget_fiscal_period' => (string) ($budgetData['budget_fiscal_period'] ?? ''),
			'budget_category' => (string) ($budgetData['budget_category'] ?? ''),
			'budget_category_id' => (int) ($budgetData['budget_category_id'] ?? 0),
			'budget_allocated_amount' => (float) ($budgetData['budget_allocated_amount'] ?? 0),
			'budget_currency' => (string) ($budgetData['budget_currency'] ?? ''),
			'budget_notes' => (string) ($budgetData['budget_notes'] ?? ''),
		];

		foreach ($updatableColumns as $columnName => $columnValue) {
			if (!in_array($columnName, $columns, true)) {
				continue;
			}

			$placeholder = ':' . $columnName;
			$setClauses[] = $columnName . ' = ' . $placeholder;
			$params[$placeholder] = $columnValue;
		}

		if (in_array('budget_updated_at', $columns, true)) {
			$setClauses[] = 'budget_updated_at = :budget_updated_at';
			$params[':budget_updated_at'] = $this->getCurrentIstDateTime();
		}

		if ($setClauses === []) {
			return false;
		}

		$sql = 'UPDATE department_budgets SET ' . implode(', ', $setClauses) . ' WHERE budget_id = :budget_id';
		$stmt = $this->db->prepare($sql);

		foreach ($params as $placeholder => $value) {
			if ($placeholder === ':budget_id' || $placeholder === ':department_id' || $placeholder === ':budget_category_id') {
				$stmt->bindValue($placeholder, (int) $value, PDO::PARAM_INT);
				continue;
			}

			if ($placeholder === ':budget_allocated_amount') {
				$stmt->bindValue($placeholder, (string) $value, PDO::PARAM_STR);
				continue;
			}

			$stmt->bindValue($placeholder, (string) $value, PDO::PARAM_STR);
		}

		try {
			return $stmt->execute();
		} catch (Throwable $error) {
			return false;
		}
	}

	public function deleteBudget(int $budgetId): bool
	{
		if ($budgetId <= 0) {
			return false;
		}

		$stmt = $this->db->prepare('DELETE FROM department_budgets WHERE budget_id = :budget_id');

		try {
			$stmt->bindValue(':budget_id', $budgetId, PDO::PARAM_INT);
			$stmt->execute();

			return $stmt->rowCount() > 0;
		} catch (Throwable $error) {
			return false;
		}
	}
}
