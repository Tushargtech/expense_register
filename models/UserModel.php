<?php

class UserModel
{
	private PDO $db;
	private ?string $userCreatedAtColumn = null;
	private bool $checkedUserCreatedAtColumn = false;

	public function __construct()
	{
		$this->db = getDB();
	}

	private function getCurrentIstDateTime(): string
	{
		$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

		return $istNow->format('Y-m-d H:i:s');
	}

	private function resolveUserCreatedAtColumn(): void
	{
		if ($this->checkedUserCreatedAtColumn) {
			return;
		}

		$this->checkedUserCreatedAtColumn = true;
		$createdCandidates = ['user_created_at', 'created_at'];

		foreach ($createdCandidates as $column) {
			try {
				$stmt = $this->db->prepare('SHOW COLUMNS FROM users LIKE :column_name');
				$stmt->execute([':column_name' => $column]);
				if ($stmt->fetch(PDO::FETCH_ASSOC) !== false) {
					$this->userCreatedAtColumn = $column;
					break;
				}
			} catch (Throwable $error) {
				break;
			}
		}

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

	public function getManagerOptions(): array
	{
		$stmt = $this->db->prepare(
			"SELECT user_id, user_name FROM users WHERE user_is_active = 1 ORDER BY user_name ASC"
		);
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function createUser(array $userData): bool
	{
		$this->resolveUserCreatedAtColumn();
		$defaultPassword = password_hash('Welcome@123', PASSWORD_BCRYPT);
		$departmentId = isset($userData['department_id']) && (int) $userData['department_id'] > 0
			? (int) $userData['department_id']
			: null;
		$managerId = isset($userData['manager_id']) && (int) $userData['manager_id'] > 0
			? (int) $userData['manager_id']
			: null;
		$isActive = isset($userData['user_is_active']) && (int) $userData['user_is_active'] === 0 ? 0 : 1;

		$columns = [
			'user_name',
			'user_email',
			'user_password_hash',
			'user_role',
			'department_id',
			'manager_id',
			'user_is_active',
		];

		$placeholders = [
			':name',
			':email',
			':password_hash',
			':role',
			':department_id',
			':manager_id',
			':user_is_active',
		];

		$params = [
			':name' => (string) ($userData['name'] ?? ''),
			':email' => (string) ($userData['email'] ?? ''),
			':password_hash' => $defaultPassword,
			':role' => (string) ($userData['role'] ?? 'employee'),
			':department_id' => $departmentId,
			':manager_id' => $managerId,
			':user_is_active' => $isActive,
		];

		if ($this->userCreatedAtColumn !== null) {
			$columns[] = $this->userCreatedAtColumn;
			$placeholders[] = ':created_at';
			$params[':created_at'] = $this->getCurrentIstDateTime();
		}

		$sql = 'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';

		$stmt = $this->db->prepare($sql);

		try {
			return $stmt->execute($params);
		} catch (Throwable $error) {
			return false;
		}
	}

	public function getUserById(int $userId): ?array
	{
		$stmt = $this->db->prepare(
			"SELECT user_id, user_name, user_email, user_role, department_id, manager_id, user_is_active
			 FROM users
			 WHERE user_id = :user_id
			 LIMIT 1"
		);
		$stmt->execute([':user_id' => $userId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row !== false ? $row : null;
	}

	public function updateUser(int $userId, array $userData): bool
	{
		$setClauses = [
			'user_name = :name',
			'user_email = :email',
			'user_role = :role',
			'department_id = :department_id',
			'manager_id = :manager_id',
			'user_is_active = :user_is_active',
		];

		$params = [
			':name' => (string) ($userData['name'] ?? ''),
			':email' => (string) ($userData['email'] ?? ''),
			':role' => (string) ($userData['role'] ?? 'employee'),
			':department_id' => (int) ($userData['department_id'] ?? 0),
			':manager_id' => (int) ($userData['manager_id'] ?? 0),
			':user_is_active' => (int) ($userData['user_is_active'] ?? 1),
			':user_id' => $userId,
		];

		$sql = "UPDATE users
				SET
					" . implode(",\n\t\t\t\t\t", $setClauses) . "
				WHERE user_id = :user_id";

		$stmt = $this->db->prepare($sql);

		try {
			return $stmt->execute($params);
		} catch (Throwable $error) {
			return false;
		}
	}
}

