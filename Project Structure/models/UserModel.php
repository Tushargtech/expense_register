<?php

class UserModel
{
	private PDO $db;

	public function __construct()
	{
		$this->db = getDB();
	}

	private function buildFilterSql(array $filters): array
	{
		$whereSql = " WHERE 1 = 1";
		$params = [];

		$search = trim((string) ($filters['search'] ?? ''));
		if ($search !== '') {
			$whereSql .= " AND (
				u.user_name LIKE :search
				OR u.user_email LIKE :search
				OR CAST(u.user_id AS CHAR) LIKE :search
			)";
			$params[':search'] = '%' . $search . '%';
		}

		$role = trim((string) ($filters['role'] ?? ''));
		if ($role !== '') {
			$whereSql .= " AND u.user_role = :role";
			$params[':role'] = $role;
		}

		$department = trim((string) ($filters['department'] ?? ''));
		if ($department !== '') {
			$whereSql .= " AND d.department_name = :department";
			$params[':department'] = $department;
		}

		$status = trim((string) ($filters['status'] ?? ''));
		if ($status !== '') {
			$whereSql .= " AND u.user_is_active = :status";
			$params[':status'] = $status === '1' ? 1 : 0;
		}

		return [$whereSql, $params];
	}

	public function getAllUsers(array $filters = [], int $limit = 10, int $offset = 0): array
	{
		[$whereSql, $params] = $this->buildFilterSql($filters);

		$sql = "SELECT
					u.user_id,
					u.user_name,
					u.user_email,
					u.user_role,
					u.department_id,
					u.manager_id,
					m.user_name AS manager_name,
					u.user_is_active,
					u.user_created_at,
					d.department_name AS dept_name
				FROM users u
				LEFT JOIN departments d ON u.department_id = d.id" .
				" LEFT JOIN users m ON u.manager_id = m.user_id" .
				$whereSql .
				" ORDER BY u.user_id DESC LIMIT :limit OFFSET :offset";

		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $value) {
			$stmt->bindValue($key, $value);
		}
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function countAllUsers(array $filters = []): int
	{
		[$whereSql, $params] = $this->buildFilterSql($filters);

		$sql = "SELECT COUNT(*)
				FROM users u
				LEFT JOIN departments d ON u.department_id = d.id" . $whereSql;

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);

		return (int) $stmt->fetchColumn();
	}

	public function getRoleOptions(): array
	{
		$stmt = $this->db->prepare(
			"SELECT DISTINCT user_role FROM users WHERE user_role IS NOT NULL AND user_role <> '' ORDER BY user_role ASC"
		);
		$stmt->execute();
		return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'user_role');
	}

	public function getDepartmentOptions(): array
	{
		$stmt = $this->db->prepare(
			"SELECT DISTINCT department_name FROM departments WHERE department_name IS NOT NULL AND department_name <> '' ORDER BY department_name ASC"
		);
		$stmt->execute();
		return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'department_name');
	}
}

