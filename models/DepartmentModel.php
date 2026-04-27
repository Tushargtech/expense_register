<?php

class DepartmentModel
{
	private PDO $db;
    private ?string $lastValidationError = null;

	public function __construct()
	{
		$this->db = getDB();
	}

	private function getCurrentIstDateTime(): string
	{
		$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

		return $istNow->format('Y-m-d H:i:s');
	}

	public function getLastValidationError(): ?string
	{
		return $this->lastValidationError;
	}

	public function getDepartmentHeadConflict(int $departmentHeadUserId, int $excludeDepartmentId = 0): ?array
	{
		if ($departmentHeadUserId <= 0) {
			return null;
		}

		$sql = "SELECT d.id, d.department_name
				FROM departments d
				WHERE d.department_head_user_id = :head_user_id";

		$params = [':head_user_id' => $departmentHeadUserId];

		if ($excludeDepartmentId > 0) {
			$sql .= " AND d.id <> :exclude_id";
			$params[':exclude_id'] = $excludeDepartmentId;
		}

		$sql .= " LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return is_array($row) ? $row : null;
	}


	public function getAllDepartments(): array
	{
		$sql = "SELECT
				d.id,
				d.department_name,
				d.department_code,
				d.department_head_user_id,
				d.department_created_at,
				u.user_name AS head_name,
				u.user_email AS head_email
			FROM departments d
			LEFT JOIN users u ON d.department_head_user_id = u.user_id
			ORDER BY d.id ASC";

		$stmt = $this->db->prepare($sql);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getDepartmentById(int $departmentId): ?array
	{
		$sql = "SELECT
				d.id,
				d.department_name,
				d.department_code,
				d.department_head_user_id,
				d.department_created_at,
				u.user_name AS head_name,
				u.user_email AS head_email
			FROM departments d
			LEFT JOIN users u ON d.department_head_user_id = u.user_id
			WHERE d.id = :id
			LIMIT 1";

		$stmt = $this->db->prepare($sql);
		$stmt->execute(['id' => $departmentId]);
		$result = $stmt->fetch(PDO::FETCH_ASSOC);

		return $result !== false ? $result : null;
	}

	
	public function createDepartment(array $departmentData): bool
	{
		$this->lastValidationError = null;

		$headUserId = (int) ($departmentData['department_head_user_id'] ?? 0);
		$conflict = $this->getDepartmentHeadConflict($headUserId);
		if ($conflict !== null) {
			$conflictDepartmentName = trim((string) ($conflict['department_name'] ?? ''));
			$this->lastValidationError = $conflictDepartmentName !== ''
				? 'Selected department head is already assigned to ' . $conflictDepartmentName . '.'
				: 'Selected department head is already assigned to another department.';
			return false;
		}

		$sql = "INSERT INTO departments (department_name, department_code, department_head_user_id, department_created_at)
			VALUES (:name, :code, :head_user_id, :created_at)";

		$stmt = $this->db->prepare($sql);

		try {
			$result = $stmt->execute([
				':name' => $departmentData['department_name'] ?? '',
				':code' => $departmentData['department_code'] ?? '',
				':head_user_id' => (int) ($departmentData['department_head_user_id'] ?? 0),
				':created_at' => $this->getCurrentIstDateTime(),
			]);

			return $result === true;
		} catch (Throwable $error) {
			return false;
		}
	}

	
	public function updateDepartment(int $departmentId, array $departmentData): bool
	{
		$this->lastValidationError = null;

		$headUserId = (int) ($departmentData['department_head_user_id'] ?? 0);
		$conflict = $this->getDepartmentHeadConflict($headUserId, $departmentId);
		if ($conflict !== null) {
			$conflictDepartmentName = trim((string) ($conflict['department_name'] ?? ''));
			$this->lastValidationError = $conflictDepartmentName !== ''
				? 'Selected department head is already assigned to ' . $conflictDepartmentName . '.'
				: 'Selected department head is already assigned to another department.';
			return false;
		}

		$setClauses = [
			'department_name = :name',
			'department_code = :code',
			'department_head_user_id = :head_user_id',
		];

		$params = [
			':id' => $departmentId,
			':name' => $departmentData['department_name'] ?? '',
			':code' => $departmentData['department_code'] ?? '',
			':head_user_id' => (int) ($departmentData['department_head_user_id'] ?? 0),
		];

		$sql = "UPDATE departments 
			SET " . implode(",\n\t\t\t\t", $setClauses) . "
			WHERE id = :id";

		$stmt = $this->db->prepare($sql);

		try {
			$result = $stmt->execute($params);

			return $result === true;
		} catch (Throwable $error) {
			return false;
		}
	}
}
