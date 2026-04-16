<?php

class BudgetCategoryModel
{
	private $db;
	private ?string $updateTimestampColumn = null;
	private bool $checkedUpdateTimestampColumn = false;

	public function __construct()
	{
		$this->db = getDB();
	}

	private function getCurrentIstDateTime(): string
	{
		$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

		return $istNow->format('Y-m-d H:i:s');
	}

	private function resolveUpdateTimestampColumn(): ?string
	{
		if ($this->checkedUpdateTimestampColumn) {
			return $this->updateTimestampColumn;
		}

		$this->checkedUpdateTimestampColumn = true;
		$candidates = ['budget_category_updated_at', 'updated_at', 'budget_category_modified_at'];

		foreach ($candidates as $column) {
			try {
				$statement = $this->db->prepare('SHOW COLUMNS FROM budget_categories LIKE :column_name');
				$statement->execute([':column_name' => $column]);
				$columnInfo = $statement->fetch(PDO::FETCH_ASSOC);

				if ($columnInfo !== false) {
					$this->updateTimestampColumn = $column;
					break;
				}
			} catch (Throwable $error) {
				$this->updateTimestampColumn = null;
				break;
			}
		}

		return $this->updateTimestampColumn;
	}

	public function getAllCategories(): array
	{
		$sql = "SELECT
				budget_category_id,
				budget_category_name,
				budget_category_code,
				budget_category_is_active
			FROM budget_categories
			ORDER BY budget_category_id ASC";

		$stmt = $this->db->prepare($sql);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getSelectableCategories(): array
	{
		$sql = "SELECT
				budget_category_id,
				budget_category_name,
				budget_category_type,
				budget_category_is_active
			FROM budget_categories
			WHERE budget_category_is_active = 1
			ORDER BY budget_category_name ASC";

		$stmt = $this->db->prepare($sql);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function countFilteredCategories(array $filters): int
	{
		$whereClauses = [];
		$params = [];

		$search = trim((string) ($filters['search'] ?? ''));
		$status = trim((string) ($filters['status'] ?? ''));
		$type = strtolower(trim((string) ($filters['type'] ?? '')));

		if ($search !== '') {
			$whereClauses[] = '(budget_category_name LIKE :search OR budget_category_code LIKE :search)';
			$params[':search'] = '%' . $search . '%';
		}

		if ($status === 'active') {
			$whereClauses[] = 'budget_category_is_active = 1';
		} elseif ($status === 'inactive') {
			$whereClauses[] = 'budget_category_is_active = 0';
		}

		if ($type === 'expense' || $type === 'purchase') {
			$whereClauses[] = 'LOWER(TRIM(budget_category_type)) = :type';
			$params[':type'] = $type;
		}

		$sql = 'SELECT COUNT(*) AS total FROM budget_categories';
		if (!empty($whereClauses)) {
			$sql .= ' WHERE ' . implode(' AND ', $whereClauses);
		}

		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $value) {
			$stmt->bindValue($key, $value, PDO::PARAM_STR);
		}
		$stmt->execute();

		return (int) $stmt->fetchColumn();
	}

	public function getFilteredCategories(array $filters, int $limit, int $offset): array
	{
		$whereClauses = [];
		$params = [];

		$search = trim((string) ($filters['search'] ?? ''));
		$status = trim((string) ($filters['status'] ?? ''));
		$type = strtolower(trim((string) ($filters['type'] ?? '')));

		if ($search !== '') {
			$whereClauses[] = '(budget_category_name LIKE :search OR budget_category_code LIKE :search)';
			$params[':search'] = '%' . $search . '%';
		}

		if ($status === 'active') {
			$whereClauses[] = 'budget_category_is_active = 1';
		} elseif ($status === 'inactive') {
			$whereClauses[] = 'budget_category_is_active = 0';
		}

		if ($type === 'expense' || $type === 'purchase') {
			$whereClauses[] = 'LOWER(TRIM(budget_category_type)) = :type';
			$params[':type'] = $type;
		}

		$sql = "SELECT
				budget_category_id,
				budget_category_name,
				budget_category_code,
				budget_category_type,
				budget_category_is_active
			FROM budget_categories";

		if (!empty($whereClauses)) {
			$sql .= ' WHERE ' . implode(' AND ', $whereClauses);
		}

		$sql .= ' ORDER BY budget_category_id ASC LIMIT :limit OFFSET :offset';

		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $value) {
			$stmt->bindValue($key, $value, PDO::PARAM_STR);
		}
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getCategoryById(int $categoryId): ?array
	{
		$sql = "SELECT
				budget_category_id,
				budget_category_name,
				budget_category_code,
				budget_category_type,
				budget_category_description,
				budget_category_is_active
			FROM budget_categories
			WHERE budget_category_id = :id
			LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$stmt->execute([':id' => $categoryId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row !== false ? $row : null;
	}

	public function createCategory(array $categoryData): bool
	{
		$createdAtIst = $this->getCurrentIstDateTime();

		$sql = "INSERT INTO budget_categories (
				budget_category_name,
				budget_category_code,
				budget_category_type,
				budget_category_description,
				budget_category_is_active,
				budget_category_created_by,
				budget_category_created_at
			) VALUES (
				:name,
				:code,
				:type,
				:description,
				:is_active,
				:created_by,
				:created_at
			)";

		$stmt = $this->db->prepare($sql);

		try {
			return $stmt->execute([
				':name' => (string) ($categoryData['budget_category_name'] ?? ''),
				':code' => (string) ($categoryData['budget_category_code'] ?? ''),
				':type' => (string) ($categoryData['budget_category_type'] ?? ''),
				':description' => (string) ($categoryData['budget_category_description'] ?? ''),
				':is_active' => (int) ($categoryData['budget_category_is_active'] ?? 1),
				':created_by' => (int) ($categoryData['budget_category_created_by'] ?? 0),
				':created_at' => $createdAtIst,
			]);
		} catch (Throwable $error) {
			return false;
		}
	}

	public function updateCategory(int $categoryId, array $categoryData): bool
	{
		$updateTimestampColumn = $this->resolveUpdateTimestampColumn();
		$setClauses = [
			'budget_category_name = :name',
			'budget_category_code = :code',
			'budget_category_type = :type',
			'budget_category_description = :description',
			'budget_category_is_active = :is_active',
		];

		$params = [
			':id' => $categoryId,
			':name' => (string) ($categoryData['budget_category_name'] ?? ''),
			':code' => (string) ($categoryData['budget_category_code'] ?? ''),
			':type' => (string) ($categoryData['budget_category_type'] ?? ''),
			':description' => (string) ($categoryData['budget_category_description'] ?? ''),
			':is_active' => (int) ($categoryData['budget_category_is_active'] ?? 1),
		];

		if ($updateTimestampColumn !== null) {
			$setClauses[] = $updateTimestampColumn . ' = :updated_at';
			$params[':updated_at'] = $this->getCurrentIstDateTime();
		}

		$sql = "UPDATE budget_categories
			SET
				" . implode(",\n\t\t\t\t", $setClauses) . "
			WHERE budget_category_id = :id";

		$stmt = $this->db->prepare($sql);

		try {
			return $stmt->execute($params);
		} catch (Throwable $error) {
			return false;
		}
	}
}
