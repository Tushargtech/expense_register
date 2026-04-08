<?php

class DepartmentModel
{
	private PDO $db;

	public function __construct()
	{
		$this->db = getDB();
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

	public function getAll(): array
	{
		return $this->getAllDepartments();
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
		$sql = "INSERT INTO departments (department_name, department_code, department_head_user_id, department_created_at)
			VALUES (:name, :code, :head_user_id, :created_at)";

		$stmt = $this->db->prepare($sql);
		
		$result = $stmt->execute([
			':name' => $departmentData['department_name'] ?? '',
			':code' => $departmentData['department_code'] ?? '',
			':head_user_id' => (int) ($departmentData['department_head_user_id'] ?? 0),
			':created_at' => date('Y-m-d H:i:s'),
		]);

		return $result === true;
	}

	
	public function updateDepartment(int $departmentId, array $departmentData): bool
	{
		$sql = "UPDATE departments 
			SET department_name = :name, 
				department_code = :code, 
				department_head_user_id = :head_user_id
			WHERE id = :id";

		$stmt = $this->db->prepare($sql);
		
		$result = $stmt->execute([
			':id' => $departmentId,
			':name' => $departmentData['department_name'] ?? '',
			':code' => $departmentData['department_code'] ?? '',
			':head_user_id' => (int) ($departmentData['department_head_user_id'] ?? 0),
		]);

		return $result === true;
	}
}
