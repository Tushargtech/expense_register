<?php

class BudgetModel
{
	private PDO $db;
	private ?array $budgetTableColumns = null;
	private ?array $budgetCategoryTableColumns = null;

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
			return false;
		}
	}
}
