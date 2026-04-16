<?php

class WorkflowModel
{
	private PDO $db;
	private string $workflowTable = 'workflows';
	private bool $checkedWorkflowTable = false;

	public function __construct()
	{
		$this->db = getDB();
	}

	private function resolveWorkflowTable(): void
	{
		if ($this->checkedWorkflowTable) {
			return;
		}

		$this->checkedWorkflowTable = true;
		$candidates = ['workflows', 'workflow', 'workflow_list'];

		foreach ($candidates as $table) {
			try {
				$stmt = $this->db->prepare('SHOW TABLES LIKE :table_name');
				$stmt->execute([':table_name' => $table]);
				if ($stmt->fetch(PDO::FETCH_NUM) !== false) {
					$this->workflowTable = $table;
					return;
				}
			} catch (Throwable $error) {
				break;
			}
		}
	}

	private function buildFilterSql(array $filters): array
	{
		$whereSql = ' WHERE 1 = 1';
		$params = [];

		$search = trim((string) ($filters['search'] ?? ''));
		if ($search !== '') {
			$whereSql .= ' AND (w.workflow_name LIKE :search OR w.workflow_type LIKE :search OR bc.budget_category_name LIKE :search)';
			$params[':search'] = '%' . $search . '%';
		}

		$status = trim((string) ($filters['status'] ?? ''));
		if ($status !== '') {
			$whereSql .= ' AND w.workflow_is_active = :status';
			$params[':status'] = $status === '1' ? 1 : 0;
		}

		$workflowType = trim((string) ($filters['workflow_type'] ?? ''));
		if ($workflowType !== '') {
			$whereSql .= ' AND w.workflow_type = :workflow_type';
			$params[':workflow_type'] = $workflowType;
		}

		$budgetCategoryId = (int) ($filters['budget_category_id'] ?? 0);
		if ($budgetCategoryId > 0) {
			$whereSql .= ' AND w.budget_category_id = :budget_category_id';
			$params[':budget_category_id'] = $budgetCategoryId;
		}

		return [$whereSql, $params];
	}

	public function getAllWorkflowTypes(): array
	{
		$this->resolveWorkflowTable();

		$sql = 'SELECT DISTINCT w.workflow_type
				FROM ' . $this->workflowTable . ' w
				WHERE w.workflow_type IS NOT NULL
				AND w.workflow_type != ""
				ORDER BY w.workflow_type ASC';

		$stmt = $this->db->prepare($sql);
		$stmt->execute();

		$types = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$type = trim((string) ($row['workflow_type'] ?? ''));
			if ($type !== '') {
				$types[] = $type;
			}
		}

		return $types;
	}

	public function getAllWorkflows(array $filters = [], int $limit = 10, int $offset = 0): array
	{
		$this->resolveWorkflowTable();
		[$whereSql, $params] = $this->buildFilterSql($filters);

		$sql = 'SELECT
					w.workflow_id,
					w.workflow_name,
					w.budget_category_id,
					bc.budget_category_name,
					step_flow.approval_flow,
					w.workflow_type,
					w.workflow_amount_min,
					w.workflow_amount_max,
					w.workflow_is_active
				FROM ' . $this->workflowTable . ' w
				LEFT JOIN budget_categories bc ON bc.budget_category_id = w.budget_category_id
				LEFT JOIN (
					SELECT
						workflow_id,
						GROUP_CONCAT(step_name ORDER BY step_order SEPARATOR " -> ") AS approval_flow
					FROM workflow_steps
					GROUP BY workflow_id
				) step_flow ON step_flow.workflow_id = w.workflow_id' .
				$whereSql .
				' ORDER BY w.workflow_id DESC LIMIT :limit OFFSET :offset';

		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $value) {
			$stmt->bindValue($key, $value);
		}
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function countAllWorkflows(array $filters = []): int
	{
		$this->resolveWorkflowTable();
		[$whereSql, $params] = $this->buildFilterSql($filters);

		$sql = 'SELECT COUNT(*)
				FROM ' . $this->workflowTable . ' w
				LEFT JOIN budget_categories bc ON bc.budget_category_id = w.budget_category_id' . $whereSql;
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);

		return (int) $stmt->fetchColumn();
	}

	public function getSelectableWorkflows(?int $budgetCategoryId = null, ?string $requestType = null): array
	{
		$this->resolveWorkflowTable();

		$sql = 'SELECT
				w.workflow_id,
				w.workflow_name,
				w.budget_category_id,
				w.workflow_type,
				w.workflow_is_active
			FROM ' . $this->workflowTable . ' w
			WHERE w.workflow_is_active = 1';

		$params = [];
		if ($budgetCategoryId !== null && $budgetCategoryId > 0) {
			$sql .= ' AND w.budget_category_id = :budget_category_id';
			$params[':budget_category_id'] = $budgetCategoryId;
		}

		$normalizedRequestType = strtolower(trim((string) ($requestType ?? '')));
		if ($normalizedRequestType !== '') {
			$sql .= ' AND LOWER(TRIM(w.workflow_type)) = :workflow_type';
			$params[':workflow_type'] = $normalizedRequestType;
		}

		$sql .= ' ORDER BY w.workflow_is_default DESC, w.workflow_name ASC';

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
		$this->resolveWorkflowTable();

		$stmt = $this->db->prepare(
			"SELECT
				workflow_id,
				workflow_name,
				budget_category_id,
				workflow_type,
				workflow_is_active,
				workflow_is_default,
				workflow_amount_min,
				workflow_amount_max
			 FROM " . $this->workflowTable . "
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
		$this->resolveWorkflowTable();
		$this->db->beginTransaction();

		try {
			$workflowSql = "INSERT INTO " . $this->workflowTable . " (
				workflow_name,
				workflow_description,
				budget_category_id,
				workflow_type,
				workflow_is_active,
				workflow_is_default,
				workflow_amount_min,
				workflow_amount_max,
				workflow_created_by
			) VALUES (
				:workflow_name,
				:workflow_description,
				:budget_category_id,
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
				':budget_category_id' => $workflowData['budget_category_id'],
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
				step_is_required,
				step_timeout_hours
			) VALUES (
				:workflow_id,
				:step_order,
				:step_name,
				:step_approver_type,
				:step_approver_role,
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
			error_log('WorkflowModel::createWorkflow failed: ' . $error->getMessage());

			return false;
		}
	}

	public function updateWorkflow(int $workflowId, array $workflowData, array $steps): bool
	{
		$this->resolveWorkflowTable();
		$this->db->beginTransaction();

		try {
			$workflowSql = "UPDATE " . $this->workflowTable . " SET
				workflow_name = :workflow_name,
				workflow_description = :workflow_description,
				budget_category_id = :budget_category_id,
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
				':budget_category_id' => $workflowData['budget_category_id'],
				':workflow_type' => $workflowData['workflow_type'],
				':workflow_is_active' => $workflowData['workflow_is_active'],
				':workflow_is_default' => $workflowData['workflow_is_default'],
				':workflow_amount_min' => $workflowData['workflow_amount_min'],
				':workflow_amount_max' => $workflowData['workflow_amount_max'],
				':workflow_id' => $workflowId,
			]);

			$existingStepsStmt = $this->db->prepare(
				'SELECT step_id FROM workflow_steps WHERE workflow_id = :workflow_id ORDER BY step_order ASC, step_id ASC'
			);
			$existingStepsStmt->execute([':workflow_id' => $workflowId]);
			$existingStepIds = array_map(
				static fn(array $row): int => (int) ($row['step_id'] ?? 0),
				$existingStepsStmt->fetchAll(PDO::FETCH_ASSOC)
			);

			$updateStepSql = "UPDATE workflow_steps SET
				step_order = :step_order,
				step_name = :step_name,
				step_approver_type = :step_approver_type,
				step_approver_role = :step_approver_role,
				step_is_required = :step_is_required,
				step_timeout_hours = :step_timeout_hours
				WHERE step_id = :step_id AND workflow_id = :workflow_id";
			$updateStepStmt = $this->db->prepare($updateStepSql);

			$insertStepSql = "INSERT INTO workflow_steps (
				workflow_id,
				step_order,
				step_name,
				step_approver_type,
				step_approver_role,
				step_is_required,
				step_timeout_hours
			) VALUES (
				:workflow_id,
				:step_order,
				:step_name,
				:step_approver_type,
				:step_approver_role,
				:step_is_required,
				:step_timeout_hours
			)";
			$insertStepStmt = $this->db->prepare($insertStepSql);

			$submittedCount = count($steps);
			$existingCount = count($existingStepIds);

			for ($index = 0; $index < $submittedCount; $index++) {
				$step = $steps[$index];
				$params = [
					':workflow_id' => $workflowId,
					':step_order' => (int) $step['step_order'],
					':step_name' => (string) $step['step_name'],
					':step_approver_type' => $step['step_approver_type'],
					':step_approver_role' => $step['step_approver_role'],
					':step_is_required' => (int) $step['step_is_required'],
					':step_timeout_hours' => $step['step_timeout_hours'],
				];

				if ($index < $existingCount) {
					$updateStepStmt->execute($params + [':step_id' => $existingStepIds[$index]]);
				} else {
					$insertStepStmt->execute($params);
				}
			}

			if ($existingCount > $submittedCount) {
				$usageCheckStmt = $this->db->prepare(
					'SELECT (
						(SELECT COUNT(*) FROM requests WHERE request_current_step_id = :step_id) +
						(SELECT COUNT(*) FROM request_actions WHERE workflow_step_id = :step_id) +
						(SELECT COUNT(*) FROM request_step_assignments WHERE workflow_step_id = :step_id)
					) AS usage_count'
				);
				$deleteUnusedStepStmt = $this->db->prepare('DELETE FROM workflow_steps WHERE step_id = :step_id AND workflow_id = :workflow_id');

				for ($index = $submittedCount; $index < $existingCount; $index++) {
					$stepId = (int) $existingStepIds[$index];
					if ($stepId <= 0) {
						continue;
					}

					$usageCheckStmt->execute([':step_id' => $stepId]);
					$usageCount = (int) ($usageCheckStmt->fetchColumn() ?: 0);
					if ($usageCount === 0) {
						$deleteUnusedStepStmt->execute([
							':step_id' => $stepId,
							':workflow_id' => $workflowId,
						]);
					}
				}
			}

			$this->db->commit();
			return true;
		} catch (Throwable $error) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			error_log('WorkflowModel::updateWorkflow failed: ' . $error->getMessage());

			return false;
		}
	}
}
