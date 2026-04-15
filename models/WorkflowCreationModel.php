<?php

class WorkflowCreationModel
{
	private PDO $db;

	public function __construct()
	{
		$this->db = getDB();
	}

	public function getActiveUsers(): array
	{
		$stmt = $this->db->prepare(
			"SELECT user_id, user_name FROM users WHERE user_is_active = 1 ORDER BY user_name ASC"
		);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getRoles(): array
	{
		$stmt = $this->db->prepare(
			"SELECT role_id, role_name, role_slug FROM roles ORDER BY role_name ASC"
		);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getWorkflowById(int $workflowId): ?array
	{
		$stmt = $this->db->prepare(
			"SELECT
				workflow_id,
				workflow_name,
				workflow_type,
				workflow_is_active,
				workflow_is_default,
				workflow_amount_min,
				workflow_amount_max
			 FROM workflows
			 WHERE workflow_id = :workflow_id
			 LIMIT 1"
		);
		$stmt->execute([':workflow_id' => $workflowId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row !== false ? $row : null;
	}

	public function getWorkflowStepsByWorkflowId(int $workflowId): array
	{
		$stmt = $this->db->prepare(
			"SELECT
				step_id,
				workflow_id,
				step_order,
				step_name,
				step_approver_type,
				step_approver_role,
				step_approver_user_id,
				step_amount_min,
				step_amount_max,
				step_is_required,
				step_timeout_hours
			 FROM workflow_steps
			 WHERE workflow_id = :workflow_id
			 ORDER BY step_order ASC, step_id ASC"
		);
		$stmt->execute([':workflow_id' => $workflowId]);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function createWorkflow(array $workflowData, array $steps): bool
	{
		$this->db->beginTransaction();

		try {
			$workflowSql = "INSERT INTO workflows (
				workflow_name,
				workflow_description,
				workflow_type,
				workflow_is_active,
				workflow_is_default,
				workflow_amount_min,
				workflow_amount_max,
				workflow_created_by
			) VALUES (
				:workflow_name,
				:workflow_description,
				:workflow_type,
				:workflow_is_active,
				:workflow_is_default,
				:workflow_amount_min,
				:workflow_amount_max,
				:workflow_created_by
			)";

			$workflowStmt = $this->db->prepare($workflowSql);
			$workflowStmt->execute([
				':workflow_name' => $workflowData['workflow_name'],
				':workflow_description' => $workflowData['workflow_description'],
				':workflow_type' => $workflowData['workflow_type'],
				':workflow_is_active' => $workflowData['workflow_is_active'],
				':workflow_is_default' => $workflowData['workflow_is_default'],
				':workflow_amount_min' => $workflowData['workflow_amount_min'],
				':workflow_amount_max' => $workflowData['workflow_amount_max'],
				':workflow_created_by' => $workflowData['workflow_created_by'],
			]);

			$workflowId = (int) $this->db->lastInsertId();

			$stepSql = "INSERT INTO workflow_steps (
				workflow_id,
				step_order,
				step_name,
				step_approver_type,
				step_approver_role,
				step_approver_user_id,
				step_amount_min,
				step_amount_max,
				step_is_required,
				step_timeout_hours
			) VALUES (
				:workflow_id,
				:step_order,
				:step_name,
				:step_approver_type,
				:step_approver_role,
				:step_approver_user_id,
				:step_amount_min,
				:step_amount_max,
				:step_is_required,
				:step_timeout_hours
			)";

			$stepStmt = $this->db->prepare($stepSql);
			foreach ($steps as $step) {
				$stepStmt->execute([
					':workflow_id' => $workflowId,
					':step_order' => (int) $step['step_order'],
					':step_name' => (string) $step['step_name'],
					':step_approver_type' => $step['step_approver_type'],
					':step_approver_role' => $step['step_approver_role'],
					':step_approver_user_id' => $step['step_approver_user_id'],
					':step_amount_min' => $step['step_amount_min'],
					':step_amount_max' => $step['step_amount_max'],
					':step_is_required' => (int) $step['step_is_required'],
					':step_timeout_hours' => $step['step_timeout_hours'],
				]);
			}

			$this->db->commit();
			return true;
		} catch (Throwable $error) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}

			return false;
		}
	}

	public function updateWorkflow(int $workflowId, array $workflowData, array $steps): bool
	{
		$this->db->beginTransaction();

		try {
			$workflowSql = "UPDATE workflows SET
				workflow_name = :workflow_name,
				workflow_description = :workflow_description,
				workflow_type = :workflow_type,
				workflow_is_active = :workflow_is_active,
				workflow_is_default = :workflow_is_default,
				workflow_amount_min = :workflow_amount_min,
				workflow_amount_max = :workflow_amount_max,
				workflow_updated_at = CURRENT_TIMESTAMP
			 WHERE workflow_id = :workflow_id";

			$workflowStmt = $this->db->prepare($workflowSql);
			$workflowStmt->execute([
				':workflow_name' => $workflowData['workflow_name'],
				':workflow_description' => $workflowData['workflow_description'],
				':workflow_type' => $workflowData['workflow_type'],
				':workflow_is_active' => $workflowData['workflow_is_active'],
				':workflow_is_default' => $workflowData['workflow_is_default'],
				':workflow_amount_min' => $workflowData['workflow_amount_min'],
				':workflow_amount_max' => $workflowData['workflow_amount_max'],
				':workflow_id' => $workflowId,
			]);

			$deleteStmt = $this->db->prepare('DELETE FROM workflow_steps WHERE workflow_id = :workflow_id');
			$deleteStmt->execute([':workflow_id' => $workflowId]);

			$stepSql = "INSERT INTO workflow_steps (
				workflow_id,
				step_order,
				step_name,
				step_approver_type,
				step_approver_role,
				step_approver_user_id,
				step_amount_min,
				step_amount_max,
				step_is_required,
				step_timeout_hours
			) VALUES (
				:workflow_id,
				:step_order,
				:step_name,
				:step_approver_type,
				:step_approver_role,
				:step_approver_user_id,
				:step_amount_min,
				:step_amount_max,
				:step_is_required,
				:step_timeout_hours
			)";

			$stepStmt = $this->db->prepare($stepSql);
			foreach ($steps as $step) {
				$stepStmt->execute([
					':workflow_id' => $workflowId,
					':step_order' => (int) $step['step_order'],
					':step_name' => (string) $step['step_name'],
					':step_approver_type' => $step['step_approver_type'],
					':step_approver_role' => $step['step_approver_role'],
					':step_approver_user_id' => $step['step_approver_user_id'],
					':step_amount_min' => $step['step_amount_min'],
					':step_amount_max' => $step['step_amount_max'],
					':step_is_required' => (int) $step['step_is_required'],
					':step_timeout_hours' => $step['step_timeout_hours'],
				]);
			}

			$this->db->commit();
			return true;
		} catch (Throwable $error) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}

			return false;
		}
	}
}
