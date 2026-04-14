<?php

class WorkflowViewModel
{
	private PDO $db;

	public function __construct()
	{
		$this->db = getDB();
	}

	public function getPendingRequestsForUser(int $userId, int $limit = 100): array
	{
		$limit = max(1, $limit);

		$sql = "SELECT
			r.request_id,
			r.request_reference_no,
			r.request_title,
			r.request_amount,
			r.request_currency,
			r.request_status,
			r.request_priority,
			r.request_submitted_at,
			r.workflow_id,
			r.request_current_step_id,
			w.workflow_name,
			w.workflow_type,
			w.workflow_amount_min,
			w.workflow_amount_max,
			submitter.user_name AS submitted_by_name,
			manager_user.user_name AS submitter_manager_name,
			current_step.step_name AS current_step_name,
			CASE
				WHEN current_step.step_approver_type = 'user' AND step_user.user_name IS NOT NULL THEN step_user.user_name
				WHEN current_step.step_approver_type = 'department_head' AND dept_head.user_name IS NOT NULL THEN dept_head.user_name
				WHEN current_step.step_approver_type = 'role' AND LOWER(COALESCE(current_step.step_approver_role, '')) = 'manager' AND manager_user.user_name IS NOT NULL THEN manager_user.user_name
				WHEN current_step.step_approver_type = 'role' AND current_step.step_approver_role IS NOT NULL AND current_step.step_approver_role != '' THEN current_step.step_approver_role
				WHEN current_step.step_approver_type IS NOT NULL AND current_step.step_approver_type != '' THEN current_step.step_approver_type
				ELSE 'System'
			END AS current_actor_name
		FROM requests r
		LEFT JOIN workflows w ON w.workflow_id = r.workflow_id
		LEFT JOIN users submitter ON submitter.user_id = r.request_submitted_by
		LEFT JOIN users manager_user ON manager_user.user_id = submitter.manager_id
		LEFT JOIN workflow_steps current_step ON current_step.step_id = r.request_current_step_id
		LEFT JOIN users step_user ON step_user.user_id = current_step.step_approver_user_id
		LEFT JOIN departments d ON d.id = r.department_id
		LEFT JOIN users dept_head ON dept_head.user_id = d.department_head_user_id
		WHERE r.request_submitted_by = :user_id
		AND r.request_status = 'pending'
		ORDER BY r.request_submitted_at DESC, r.request_id DESC
		LIMIT :limit";

		$stmt = $this->db->prepare($sql);
		$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getWorkflowStepsByWorkflowIds(array $workflowIds): array
	{
		$normalized = [];
		foreach ($workflowIds as $workflowId) {
			$id = (int) $workflowId;
			if ($id > 0) {
				$normalized[$id] = true;
			}
		}

		$ids = array_keys($normalized);
		if (count($ids) === 0) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$sql = "SELECT
			ws.step_id,
			ws.workflow_id,
			ws.step_order,
			ws.step_name,
			ws.step_approver_type,
			ws.step_approver_role,
			ws.step_approver_user_id,
			u.user_name AS approver_user_name
		FROM workflow_steps ws
		LEFT JOIN users u ON u.user_id = ws.step_approver_user_id
		WHERE ws.workflow_id IN ($placeholders)
		ORDER BY ws.workflow_id ASC, ws.step_order ASC, ws.step_id ASC";

		$stmt = $this->db->prepare($sql);
		foreach ($ids as $index => $id) {
			$stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
		}
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$stepsMap = [];
		foreach ($rows as $row) {
			$workflowId = (int) ($row['workflow_id'] ?? 0);
			if (!isset($stepsMap[$workflowId])) {
				$stepsMap[$workflowId] = [];
			}

			$stepsMap[$workflowId][] = [
				'step_id' => (int) ($row['step_id'] ?? 0),
				'step_order' => (int) ($row['step_order'] ?? 1),
				'step_name' => (string) ($row['step_name'] ?? ''),
				'step_approver_type' => (string) ($row['step_approver_type'] ?? ''),
				'step_approver_role' => (string) ($row['step_approver_role'] ?? ''),
				'step_approver_user_id' => (int) ($row['step_approver_user_id'] ?? 0),
				'approver_user_name' => (string) ($row['approver_user_name'] ?? ''),
			];
		}

		return $stepsMap;
	}
}
