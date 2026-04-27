<?php

class WorkflowModel
{
	private PDO $db;
	private string $workflowTable = 'workflows';
	private bool $checkedWorkflowTable = false;
	private array $autoIncrementSupport = [];

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

	private function dropWorkflowStepReferenceForeignKeys(): void
	{
		try {
			$stmt = $this->db->prepare(
				"SELECT kcu.TABLE_NAME, kcu.CONSTRAINT_NAME
				 FROM information_schema.KEY_COLUMN_USAGE kcu
				 WHERE kcu.TABLE_SCHEMA = DATABASE()
				   AND kcu.REFERENCED_TABLE_NAME = 'workflow_steps'
				   AND kcu.TABLE_NAME IN ('requests', 'request_actions', 'request_step_assignments')"
			);
			$stmt->execute();
			$constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);

			foreach ($constraints as $constraint) {
				$tableName = (string) ($constraint['TABLE_NAME'] ?? '');
				$constraintName = (string) ($constraint['CONSTRAINT_NAME'] ?? '');
				if ($tableName === '' || $constraintName === '') {
					continue;
				}

				$this->db->exec("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`");
			}
		} catch (Throwable $error) {
			error_log('WorkflowModel::dropWorkflowStepReferenceForeignKeys failed: ' . $error->getMessage());
		}
	}

	private function supportsAutoIncrement(string $tableName, string $columnName): bool
	{
		$cacheKey = $tableName . '.' . $columnName;
		if (array_key_exists($cacheKey, $this->autoIncrementSupport)) {
			return $this->autoIncrementSupport[$cacheKey];
		}

		try {
			$stmt = $this->db->prepare(
				'SELECT EXTRA
				 FROM information_schema.COLUMNS
				 WHERE TABLE_SCHEMA = DATABASE()
				   AND TABLE_NAME = :table_name
				   AND COLUMN_NAME = :column_name
				 LIMIT 1'
			);
			$stmt->execute([
				':table_name' => $tableName,
				':column_name' => $columnName,
			]);
			$extra = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));
			$this->autoIncrementSupport[$cacheKey] = str_contains($extra, 'auto_increment');
		} catch (Throwable $error) {
			$this->autoIncrementSupport[$cacheKey] = false;
		}

		return $this->autoIncrementSupport[$cacheKey];
	}

	private function reserveNextId(string $tableName, string $columnName): int
	{
		$stmt = $this->db->query(
			'SELECT `' . $columnName . '`
			 FROM `' . $tableName . '`
			 ORDER BY `' . $columnName . '` DESC
			 LIMIT 1
			 FOR UPDATE'
		);
		$currentId = (int) ($stmt->fetchColumn() ?: 0);

		return $currentId + 1;
	}

	private function buildFilterSql(array $filters): array
	{
		$whereSql = ' WHERE 1 = 1';
		$params = [];

		$search = trim((string) ($filters['search'] ?? ''));
		if ($search !== '') {
			$whereSql .= ' AND (w.workflow_name LIKE :search OR w.workflow_type LIKE :search)';
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
					step_flow.approval_flow,
					w.workflow_type,
					w.workflow_amount_min,
					w.workflow_amount_max,
					w.workflow_is_active
				FROM ' . $this->workflowTable . ' w
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
				FROM ' . $this->workflowTable . ' w' . $whereSql;
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
				w.workflow_type,
				w.workflow_is_active
			FROM ' . $this->workflowTable . ' w
			WHERE w.workflow_is_active = 1';

		$params = [];

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

	public function getWorkflowForRequestCriteria(string $requestType, float $amount): ?array
	{
		$this->resolveWorkflowTable();

		$normalizedRequestType = strtolower(trim($requestType));
		if ($normalizedRequestType === '' || $amount <= 0) {
			return null;
		}

		$sql = 'SELECT
				w.workflow_id,
				w.workflow_name,
				w.workflow_type,
				w.workflow_is_active,
				w.workflow_is_default,
				w.workflow_amount_min,
				w.workflow_amount_max
			FROM ' . $this->workflowTable . ' w
			WHERE w.workflow_is_active = 1
			  AND LOWER(TRIM(w.workflow_type)) = :workflow_type
			  AND (w.workflow_amount_min IS NULL OR w.workflow_amount_min <= :request_amount)
			  AND (w.workflow_amount_max IS NULL OR w.workflow_amount_max >= :request_amount)
			ORDER BY
				w.workflow_is_default DESC,
				CASE
					WHEN w.workflow_amount_min IS NULL OR w.workflow_amount_max IS NULL THEN 999999999999
					ELSE ABS(w.workflow_amount_max - w.workflow_amount_min)
				END ASC,
				COALESCE(w.workflow_amount_min, 0) ASC,
				w.workflow_id ASC
			LIMIT 1';

		$stmt = $this->db->prepare($sql);
		$stmt->execute([
			':workflow_type' => $normalizedRequestType,
			':request_amount' => $amount,
		]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row !== false ? $row : null;
	}

	public function getActiveUsers(): array
	{
		$stmt = $this->db->prepare(
			"SELECT
				u.user_id,
				u.user_name,
				u.user_role,
				CASE
					WHEN u.user_role IN ('admin', 'finance', 'hr', 'employee') THEN u.user_role
					WHEN EXISTS (
						SELECT 1
						FROM departments dh
						WHERE dh.department_head_user_id = u.user_id
						LIMIT 1
					) THEN 'department_head'
					WHEN EXISTS (
						SELECT 1
						FROM users m
						WHERE m.manager_id = u.user_id
						  AND m.user_is_active = 1
						LIMIT 1
					) THEN 'manager'
					ELSE u.user_role
				END AS approver_role
			 FROM users u
			 WHERE u.user_is_active = 1
			 ORDER BY approver_role ASC, u.user_name ASC"
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
				step_approver_user_id,
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

			$workflowUsesAutoIncrement = $this->supportsAutoIncrement($this->workflowTable, 'workflow_id');
			$workflowId = null;

			if ($workflowUsesAutoIncrement) {
				$workflowSql = "INSERT INTO " . $this->workflowTable . " (
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
			} else {
				$workflowId = $this->reserveNextId($this->workflowTable, 'workflow_id');
				$workflowSql = "INSERT INTO " . $this->workflowTable . " (
					workflow_id,
					workflow_name,
					workflow_description,
					workflow_type,
					workflow_is_active,
					workflow_is_default,
					workflow_amount_min,
					workflow_amount_max,
					workflow_created_by
				) VALUES (
					:workflow_id,
					:workflow_name,
					:workflow_description,
					:workflow_type,
					:workflow_is_active,
					:workflow_is_default,
					:workflow_amount_min,
					:workflow_amount_max,
					:workflow_created_by
				)";
			}


			$workflowStmt = $this->db->prepare($workflowSql);
			$workflowParams = [
				':workflow_name' => $workflowData['workflow_name'],
				':workflow_description' => $workflowData['workflow_description'],
				':workflow_type' => $workflowData['workflow_type'],
				':workflow_is_active' => $workflowData['workflow_is_active'],
				':workflow_is_default' => $workflowData['workflow_is_default'],
				':workflow_amount_min' => $workflowData['workflow_amount_min'],
				':workflow_amount_max' => $workflowData['workflow_amount_max'],
				':workflow_created_by' => $workflowData['workflow_created_by'],
			];
			if (!$workflowUsesAutoIncrement) {
				$workflowParams[':workflow_id'] = $workflowId;
			}
			$workflowStmt->execute($workflowParams);

			if ($workflowUsesAutoIncrement) {
				$workflowId = (int) $this->db->lastInsertId();
			}

			$stepUsesAutoIncrement = $this->supportsAutoIncrement('workflow_steps', 'step_id');
			if ($stepUsesAutoIncrement) {
				$stepSql = "INSERT INTO workflow_steps (
					workflow_id,
					step_order,
					step_name,
					step_approver_type,
					step_approver_role,
					step_approver_user_id,
					step_is_required,
					step_timeout_hours
				) VALUES (
					:workflow_id,
					:step_order,
					:step_name,
					:step_approver_type,
					:step_approver_role,
					:step_approver_user_id,
					:step_is_required,
					:step_timeout_hours
				)";
			} else {
				$stepSql = "INSERT INTO workflow_steps (
					step_id,
					workflow_id,
					step_order,
					step_name,
					step_approver_type,
					step_approver_role,
					step_approver_user_id,
					step_is_required,
					step_timeout_hours
				) VALUES (
					:step_id,
					:workflow_id,
					:step_order,
					:step_name,
					:step_approver_type,
					:step_approver_role,
					:step_approver_user_id,
					:step_is_required,
					:step_timeout_hours
				)";
			}

			$stepStmt = $this->db->prepare($stepSql);
			foreach ($steps as $step) {
				$stepParams = [
					':workflow_id' => $workflowId,
					':step_order' => (int) $step['step_order'],
					':step_name' => (string) $step['step_name'],
					':step_approver_type' => $step['step_approver_type'],
					':step_approver_role' => $step['step_approver_role'],
					':step_approver_user_id' => (int) ($step['step_approver_user_id'] ?? 0) > 0 ? (int) $step['step_approver_user_id'] : null,
					':step_is_required' => (int) $step['step_is_required'],
					':step_timeout_hours' => $step['step_timeout_hours'],
				];
				if (!$stepUsesAutoIncrement) {
					$stepParams[':step_id'] = $this->reserveNextId('workflow_steps', 'step_id');
				}
				$stepStmt->execute($stepParams);
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
		$this->dropWorkflowStepReferenceForeignKeys();
		$this->db->beginTransaction();

		try {
			$workflowExistsStmt = $this->db->prepare('SELECT 1 FROM ' . $this->workflowTable . ' WHERE workflow_id = :workflow_id LIMIT 1');
			$workflowExistsStmt->execute([':workflow_id' => $workflowId]);
			if ($workflowExistsStmt->fetchColumn() === false) {
				throw new Exception("Workflow ID {$workflowId} not found.");
			}

			$workflowSql = "UPDATE " . $this->workflowTable . " SET
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
				step_approver_user_id = :step_approver_user_id,
				step_is_required = :step_is_required,
				step_timeout_hours = :step_timeout_hours
				WHERE step_id = :step_id AND workflow_id = :workflow_id";
			$updateStepStmt = $this->db->prepare($updateStepSql);

			$stepUsesAutoIncrement = $this->supportsAutoIncrement('workflow_steps', 'step_id');
			if ($stepUsesAutoIncrement) {
				$insertStepSql = "INSERT INTO workflow_steps (
					workflow_id,
					step_order,
					step_name,
					step_approver_type,
					step_approver_role,
					step_approver_user_id,
					step_is_required,
					step_timeout_hours
				) VALUES (
					:workflow_id,
					:step_order,
					:step_name,
					:step_approver_type,
					:step_approver_role,
					:step_approver_user_id,
					:step_is_required,
					:step_timeout_hours
				)";
			} else {
				$insertStepSql = "INSERT INTO workflow_steps (
					step_id,
					workflow_id,
					step_order,
					step_name,
					step_approver_type,
					step_approver_role,
					step_approver_user_id,
					step_is_required,
					step_timeout_hours
				) VALUES (
					:step_id,
					:workflow_id,
					:step_order,
					:step_name,
					:step_approver_type,
					:step_approver_role,
					:step_approver_user_id,
					:step_is_required,
					:step_timeout_hours
				)";
			}
			$insertStepStmt = $this->db->prepare($insertStepSql);

			$submittedStepIds = [];

			foreach ($steps as $step) {
				$stepId = (int) ($step['step_id'] ?? 0);
				$params = [
					':workflow_id' => $workflowId,
					':step_order' => (int) $step['step_order'],
					':step_name' => (string) $step['step_name'],
					':step_approver_type' => $step['step_approver_type'],
					':step_approver_role' => $step['step_approver_role'],
					':step_approver_user_id' => (int) ($step['step_approver_user_id'] ?? 0) > 0 ? (int) $step['step_approver_user_id'] : null,
					':step_is_required' => (int) $step['step_is_required'],
					':step_timeout_hours' => $step['step_timeout_hours'],
				];

				if ($stepId > 0) {
					if (!in_array($stepId, $existingStepIds, true)) {
						throw new Exception("Step ID {$stepId} does not belong to workflow {$workflowId}.");
					}

					$submittedStepIds[] = $stepId;
					$updateStepStmt->execute($params + [':step_id' => $stepId]);
				} else {
					if (!$stepUsesAutoIncrement) {
						$params[':step_id'] = $this->reserveNextId('workflow_steps', 'step_id');
					}
					$insertStepStmt->execute($params);
				}
			}

			if ($existingStepIds !== []) {
				$stepsToDelete = array_diff($existingStepIds, $submittedStepIds);
				if ($stepsToDelete !== []) {
					$deleteUnusedStepStmt = $this->db->prepare('DELETE FROM workflow_steps WHERE step_id = :step_id AND workflow_id = :workflow_id');

					foreach ($stepsToDelete as $stepId) {
						$stepId = (int) $stepId;
						if ($stepId <= 0) {
							continue;
						}

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
