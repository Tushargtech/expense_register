<?php

class BudgetCategoryModel
{
	private $db;

	public function __construct()
	{
		$this->db = getDB();
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

	public function countFilteredCategories(array $filters): int
	{
		$whereClauses = [];
		$params = [];

		$search = trim((string) ($filters['search'] ?? ''));
		$status = trim((string) ($filters['status'] ?? ''));

		if ($search !== '') {
			$whereClauses[] = '(budget_category_name LIKE :search OR budget_category_code LIKE :search)';
			$params[':search'] = '%' . $search . '%';
		}

		if ($status === 'active') {
			$whereClauses[] = 'budget_category_is_active = 1';
		} elseif ($status === 'inactive') {
			$whereClauses[] = 'budget_category_is_active = 0';
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

		if ($search !== '') {
			$whereClauses[] = '(budget_category_name LIKE :search OR budget_category_code LIKE :search)';
			$params[':search'] = '%' . $search . '%';
		}

		if ($status === 'active') {
			$whereClauses[] = 'budget_category_is_active = 1';
		} elseif ($status === 'inactive') {
			$whereClauses[] = 'budget_category_is_active = 0';
		}

		$sql = "SELECT
				budget_category_id,
				budget_category_name,
				budget_category_code,
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
}
