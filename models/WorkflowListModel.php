<?php

class WorkflowListModel
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
			$whereSql .= ' AND (w.workflow_name LIKE :search OR w.workflow_type LIKE :search)';
			$params[':search'] = '%' . $search . '%';
		}

		$status = trim((string) ($filters['status'] ?? ''));
		if ($status !== '') {
			$whereSql .= ' AND w.workflow_is_active = :status';
			$params[':status'] = $status === '1' ? 1 : 0;
		}

		return [$whereSql, $params];
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

		$sql = 'SELECT COUNT(*) FROM ' . $this->workflowTable . ' w' . $whereSql;
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);

		return (int) $stmt->fetchColumn();
	}
}
