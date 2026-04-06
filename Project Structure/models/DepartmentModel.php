<?php

class DepartmentModel
{
	private PDO $db;

	public function __construct()
	{
		$this->db = getDB();
	}

	public function getAll(): array
	{
		$stmt = $this->db->prepare(
			"SELECT id, department_name FROM departments ORDER BY department_name ASC"
		);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
}
