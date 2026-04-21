<?php

class ExpenseModel
{
    private $db;
    private ?bool $requestWorkflowSnapshotAvailable = null;

    public function __construct()
    {
        $this->db = getDB();
    }

    private function ensureRequestWorkflowSnapshotTable(): bool
    {
        if ($this->requestWorkflowSnapshotAvailable !== null) {
            return $this->requestWorkflowSnapshotAvailable;
        }

        try {
            $tableCheckStmt = $this->db->query("SHOW TABLES LIKE 'request_workflow_steps'");
            if ($tableCheckStmt !== false && $tableCheckStmt->fetch(PDO::FETCH_NUM) !== false) {
                $this->requestWorkflowSnapshotAvailable = true;
                return true;
            }

            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS request_workflow_steps (
                    request_workflow_step_id INT NOT NULL AUTO_INCREMENT,
                    request_id INT NOT NULL,
                    workflow_step_id INT NOT NULL,
                    step_order INT NOT NULL,
                    step_name VARCHAR(150) DEFAULT NULL,
                    step_approver_type VARCHAR(50) DEFAULT NULL,
                    step_approver_role VARCHAR(50) DEFAULT NULL,
                    step_approver_user_id INT DEFAULT NULL,
                    step_is_required TINYINT(1) DEFAULT 1,
                    step_timeout_hours INT DEFAULT NULL,
                    step_created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (request_workflow_step_id),
                    KEY idx_request_workflow_steps_request_id (request_id),
                    KEY idx_request_workflow_steps_step_id (workflow_step_id),
                    CONSTRAINT request_workflow_steps_ibfk_1 FOREIGN KEY (request_id) REFERENCES requests (request_id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );

            $tableCheckStmt = $this->db->query("SHOW TABLES LIKE 'request_workflow_steps'");
            $this->requestWorkflowSnapshotAvailable = $tableCheckStmt !== false && $tableCheckStmt->fetch(PDO::FETCH_NUM) !== false;

            return $this->requestWorkflowSnapshotAvailable;
        } catch (Throwable $error) {
            $this->requestWorkflowSnapshotAvailable = false;
            error_log('ExpenseModel::ensureRequestWorkflowSnapshotTable failed: ' . $error->getMessage());

            return false;
        }
    }

    private function snapshotWorkflowStepsForRequest(int $requestId, int $workflowId): array
    {
        if ($requestId <= 0 || $workflowId <= 0) {
            return [];
        }

        if (!$this->ensureRequestWorkflowSnapshotTable()) {
            return [];
        }

        $existing = $this->getRequestWorkflowSteps($requestId);
        if ($existing !== []) {
            return $existing;
        }

        $stepsStmt = $this->db->prepare(
            "SELECT
                step_id,
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
        $stepsStmt->execute([':workflow_id' => $workflowId]);
        $steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($steps === []) {
            return [];
        }

        $insertStmt = $this->db->prepare(
            "INSERT INTO request_workflow_steps (
                request_id,
                workflow_step_id,
                step_order,
                step_name,
                step_approver_type,
                step_approver_role,
                step_approver_user_id,
                step_is_required,
                step_timeout_hours
            ) VALUES (
                :request_id,
                :workflow_step_id,
                :step_order,
                :step_name,
                :step_approver_type,
                :step_approver_role,
                :step_approver_user_id,
                :step_is_required,
                :step_timeout_hours
            )"
        );

        foreach ($steps as $step) {
            $insertStmt->execute([
                ':request_id' => $requestId,
                ':workflow_step_id' => (int) ($step['step_id'] ?? 0),
                ':step_order' => (int) ($step['step_order'] ?? 0),
                ':step_name' => $step['step_name'] ?? null,
                ':step_approver_type' => $step['step_approver_type'] ?? null,
                ':step_approver_role' => $step['step_approver_role'] ?? null,
                ':step_approver_user_id' => (int) ($step['step_approver_user_id'] ?? 0) > 0 ? (int) $step['step_approver_user_id'] : null,
                ':step_is_required' => (int) ($step['step_is_required'] ?? 1),
                ':step_timeout_hours' => $step['step_timeout_hours'] !== null && $step['step_timeout_hours'] !== '' ? (int) $step['step_timeout_hours'] : null,
            ]);
        }

        return $this->getRequestWorkflowSteps($requestId);
    }

    private function getRequestWorkflowSteps(int $requestId): array
    {
        if ($requestId <= 0 || !$this->ensureRequestWorkflowSnapshotTable()) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT
                workflow_step_id AS step_id,
                step_order,
                step_name,
                step_approver_type,
                step_approver_role,
                step_approver_user_id,
                step_is_required,
                step_timeout_hours
             FROM request_workflow_steps
             WHERE request_id = :request_id
             ORDER BY step_order ASC, workflow_step_id ASC"
        );
        $stmt->execute([':request_id' => $requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRequestWorkflowStepById(int $requestId, int $workflowStepId): ?array
    {
        if ($requestId <= 0 || $workflowStepId <= 0 || !$this->ensureRequestWorkflowSnapshotTable()) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT
                workflow_step_id AS step_id,
                step_order,
                step_name,
                step_approver_type,
                step_approver_role,
                step_approver_user_id,
                step_is_required,
                step_timeout_hours
             FROM request_workflow_steps
             WHERE request_id = :request_id
               AND workflow_step_id = :workflow_step_id
             LIMIT 1"
        );
        $stmt->execute([
            ':request_id' => $requestId,
            ':workflow_step_id' => $workflowStepId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private function getNextRequestWorkflowStep(int $requestId, int $currentStepId): ?array
    {
        if ($requestId <= 0 || $currentStepId <= 0 || !$this->ensureRequestWorkflowSnapshotTable()) {
            return null;
        }

        $currentStep = $this->getRequestWorkflowStepById($requestId, $currentStepId);
        if (!is_array($currentStep)) {
            return null;
        }

        $currentStepOrder = (int) ($currentStep['step_order'] ?? 0);
        if ($currentStepOrder <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT
                workflow_step_id AS step_id,
                step_order,
                step_name,
                step_approver_type,
                step_approver_role,
                step_approver_user_id,
                step_is_required,
                step_timeout_hours
             FROM request_workflow_steps
             WHERE request_id = :request_id
               AND step_order > :current_step_order
             ORDER BY step_order ASC, workflow_step_id ASC
             LIMIT 1"
        );
        $stmt->execute([
            ':request_id' => $requestId,
            ':current_step_order' => $currentStepOrder,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function getCurrentRequestStepDefinition(int $requestId, int $currentStepId, int $workflowId): ?array
    {
        if ($requestId <= 0 || $currentStepId <= 0) {
            return null;
        }

        $snapshotStep = $this->getRequestWorkflowStepById($requestId, $currentStepId);
        if (is_array($snapshotStep)) {
            return $snapshotStep;
        }

        if ($workflowId <= 0) {
            return $this->getWorkflowStepById($currentStepId);
        }

        return $this->getWorkflowStepById($currentStepId);
    }

    public function getExpenses(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        $userId = (int) ($_SESSION['auth']['user_id'] ?? 0);
        $requestScope = strtolower(trim((string) ($filters['request_scope'] ?? 'all')));
        $selectedStatus = strtolower(trim((string) ($filters['status'] ?? '')));
        if (!in_array($requestScope, ['all', 'my_requests', 'others'], true)) {
            $requestScope = 'all';
        }

        if (!empty($filters['search'])) {
            $where[] = "(r.request_title LIKE ? OR r.request_description LIKE ?)";
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        if ($requestScope === 'my_requests') {
            $where[] = 'r.request_submitted_by = ?';
            $params[] = $userId;
        } elseif ($requestScope === 'others') {
            $where[] = 'r.request_submitted_by <> ?';
            $params[] = $userId;
            if (in_array($selectedStatus, ['approved', 'rejected'], true)) {
                $actionType = $selectedStatus === 'approved' ? 'approve' : 'reject';
                $where[] = 'EXISTS (
                    SELECT 1
                    FROM request_actions ra
                    WHERE ra.request_id = r.request_id
                      AND ra.action_actor_id = ?
                      AND ra.action = ?
                )';
                $params[] = $userId;
                $params[] = $actionType;
            } else {
                $where[] = 'r.request_status = ?';
                $params[] = 'pending';
                $where[] = 'EXISTS (
                    SELECT 1
                    FROM request_step_assignments rsa
                    WHERE rsa.request_id = r.request_id
                      AND rsa.workflow_step_id = r.request_current_step_id
                      AND rsa.request_step_assigned_to = ?
                      AND rsa.request_step_status = ?
                )';
                $params[] = $userId;
                $params[] = 'pending';
            }
        } else {
            $where[] = '(
                r.request_submitted_by = ?
                OR (
                    r.request_submitted_by <> ?
                    AND r.request_status = ?
                    AND EXISTS (
                        SELECT 1
                        FROM request_step_assignments rsa
                        WHERE rsa.request_id = r.request_id
                          AND rsa.workflow_step_id = r.request_current_step_id
                          AND rsa.request_step_assigned_to = ?
                          AND rsa.request_step_status = ?
                    )
                )
            )';
            $params[] = $userId;
            $params[] = $userId;
            $params[] = 'pending';
            $params[] = $userId;
            $params[] = 'pending';
        }

        if (!empty($filters['type'])) {
            $where[] = "r.request_type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['department'])) {
            $where[] = "r.department_id = ?";
            $params[] = $filters['department'];
        }

        if (!empty($filters['status'])) {
            $where[] = "r.request_status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "r.request_submitted_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = "r.request_submitted_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT r.*, d.department_name, u.user_name as submitter_name, u.user_email as submitter_email
                FROM requests r
                LEFT JOIN departments d ON r.department_id = d.id
                LEFT JOIN users u ON r.request_submitted_by = u.user_id
                WHERE $whereClause
                ORDER BY r.request_submitted_at DESC
                LIMIT $perPage OFFSET $offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM requests r WHERE $whereClause";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'expenses' => $expenses,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    public function hasPendingAssignmentForUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $sql = "SELECT 1
                FROM request_step_assignments
                WHERE request_step_assigned_to = :user_id
                  AND request_step_status = 'pending'
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchColumn() !== false;
    }

    public function getDashboardKpis(RbacService $rbac): array
    {
        $currentUserId = $rbac->userId();

        if ($currentUserId <= 0) {
            return [
                'total_requests' => 0,
                'accepted_requests' => 0,
                'rejected_requests' => 0,
                'total_expense' => 0.0,
            ];
        }

        $sql = "SELECT
                    SUM(CASE WHEN LOWER(TRIM(r.request_status)) = 'pending' THEN 1 ELSE 0 END) AS total_requests,
                    SUM(CASE WHEN LOWER(TRIM(r.request_status)) IN ('approved', 'accepted') THEN 1 ELSE 0 END) AS accepted_requests,
                    SUM(CASE WHEN LOWER(TRIM(r.request_status)) = 'rejected' THEN 1 ELSE 0 END) AS rejected_requests,
                    SUM(CASE WHEN LOWER(TRIM(r.request_status)) IN ('approved', 'accepted') THEN r.request_amount ELSE 0 END) AS total_approved_expense
                FROM requests r
                WHERE r.request_submitted_by = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$currentUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $kpis = [
            'total_requests' => (int) ($row['total_requests'] ?? 0),
            'accepted_requests' => (int) ($row['accepted_requests'] ?? 0),
            'rejected_requests' => (int) ($row['rejected_requests'] ?? 0),
            'total_expense' => (float) ($row['total_approved_expense'] ?? 0),
        ];

        return $kpis;
    }

    public function getTodayRecentActivity(RbacService $rbac, int $limit = 12): array
    {
        $currentUserId = $rbac->userId();
        if ($currentUserId <= 0) {
            return [];
        }

        $sql = "(
                    SELECT
                        r.request_id,
                        r.request_reference_no,
                        r.request_title,
                        r.request_type,
                        r.request_amount,
                        r.request_currency,
                        r.request_status,
                        r.request_submitted_at AS activity_at,
                        'create' AS activity_type,
                        submitter.user_name AS actor_name,
                        '' AS activity_comment
                    FROM requests r
                    LEFT JOIN users submitter ON submitter.user_id = r.request_submitted_by
                                        WHERE r.request_submitted_by = ?
                      AND DATE(r.request_submitted_at) = CURDATE()
                )
                UNION ALL
                (
                    SELECT
                        r.request_id,
                        r.request_reference_no,
                        r.request_title,
                        r.request_type,
                        r.request_amount,
                        r.request_currency,
                        r.request_status,
                        ra.acted_at AS activity_at,
                        ra.action AS activity_type,
                        actor.user_name AS actor_name,
                        COALESCE(ra.action_comment, '') AS activity_comment
                    FROM request_actions ra
                    INNER JOIN requests r ON r.request_id = ra.request_id
                    LEFT JOIN users actor ON actor.user_id = ra.action_actor_id
                                        WHERE ra.action_actor_id = ?
                      AND DATE(ra.acted_at) = CURDATE()
                )
                ORDER BY activity_at DESC
                LIMIT " . (int) max(1, $limit);

                $params = [$currentUserId, $currentUserId];
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findDepartmentHeadUserId(int $departmentId): ?int
    {
        if ($departmentId <= 0) {
            return null;
        }

        $sql = "SELECT d.department_head_user_id
                FROM departments d
                INNER JOIN users u ON u.user_id = d.department_head_user_id
                WHERE d.id = :department_id
                  AND u.user_is_active = 1
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':department_id' => $departmentId]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return null;
        }

        $userId = (int) $value;
        return $userId > 0 ? $userId : null;
    }

    public function findManagerAssigneeForDepartment(int $requesterId, int $departmentId): ?int
    {
        if ($departmentId <= 0) {
            return null;
        }

        if ($requesterId > 0) {
            $directSql = "SELECT m.user_id
                          FROM users u
                          INNER JOIN users m ON m.user_id = u.manager_id
                          WHERE u.user_id = :requester_id
                            AND u.department_id = :department_id
                            AND m.user_is_active = 1
                          LIMIT 1";
            $directStmt = $this->db->prepare($directSql);
            $directStmt->execute([
                ':requester_id' => $requesterId,
                ':department_id' => $departmentId,
            ]);
            $directManager = $directStmt->fetchColumn();
            if ($directManager !== false) {
                $managerId = (int) $directManager;
                if ($managerId > 0) {
                    return $managerId;
                }
            }
        }

        $fallbackSql = "SELECT m.user_id
                        FROM users m
                        WHERE m.user_is_active = 1
                          AND m.department_id = :department_id
                          AND EXISTS (
                              SELECT 1
                              FROM users r
                              WHERE r.manager_id = m.user_id
                                AND r.user_is_active = 1
                                AND r.department_id = :department_id
                          )
                        ORDER BY m.user_id ASC
                        LIMIT 1";
        $fallbackStmt = $this->db->prepare($fallbackSql);
        $fallbackStmt->execute([':department_id' => $departmentId]);
        $fallbackManager = $fallbackStmt->fetchColumn();

        if ($fallbackManager === false) {
            return null;
        }

        $managerId = (int) $fallbackManager;
        return $managerId > 0 ? $managerId : null;
    }

    private function getCurrentIstDateTime(): string
    {
        $istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

        return $istNow->format('Y-m-d H:i:s');
    }

    private function normalizeRequestTypeForStorage(string $requestType): string
    {
        $normalized = strtolower(trim($requestType));

        return match ($normalized) {
            'expense', 'reimbursable' => 'expense',
            'purchase', 'company paid', 'company_paid' => 'purchase',
            'other' => 'other',
            default => 'expense',
        };
    }

    public function createRequest(array $requestData, ?array $attachmentData = null): int
    {
        $this->db->beginTransaction();

        try {
            $normalizedRequestType = $this->normalizeRequestTypeForStorage((string) ($requestData['request_type'] ?? ''));

            $requestSql = "INSERT INTO requests (
                    request_reference_no,
                    request_type,
                    request_title,
                    request_description,
                    request_amount,
                    request_currency,
                    department_id,
                    request_category,
                    budget_category_id,
                    workflow_id,
                    request_current_step_id,
                    request_submitted_by,
                    request_status,
                    request_priority,
                    request_notes,
                    request_submitted_at
                ) VALUES (
                    :request_reference_no,
                    :request_type,
                    :request_title,
                    :request_description,
                    :request_amount,
                    :request_currency,
                    :department_id,
                    :request_category,
                    :budget_category_id,
                    :workflow_id,
                    :request_current_step_id,
                    :request_submitted_by,
                    :request_status,
                    :request_priority,
                    :request_notes,
                    :request_submitted_at
                )";

            $requestStmt = $this->db->prepare($requestSql);
            $requestStmt->execute([
                ':request_reference_no' => (string) ($requestData['request_reference_no'] ?? ''),
                ':request_type' => $normalizedRequestType,
                ':request_title' => (string) ($requestData['request_title'] ?? ''),
                ':request_description' => $requestData['request_description'] ?? null,
                ':request_amount' => (float) ($requestData['request_amount'] ?? 0),
                ':request_currency' => (string) ($requestData['request_currency'] ?? 'INR'),
                ':department_id' => (int) ($requestData['department_id'] ?? 0),
                ':request_category' => (string) ($requestData['request_category'] ?? ''),
                ':budget_category_id' => (int) ($requestData['budget_category_id'] ?? 0),
                ':workflow_id' => (int) ($requestData['workflow_id'] ?? 0),
                ':request_current_step_id' => $requestData['request_current_step_id'] !== null
                    ? (int) $requestData['request_current_step_id']
                    : null,
                ':request_submitted_by' => (int) ($requestData['request_submitted_by'] ?? 0),
                ':request_status' => (string) ($requestData['request_status'] ?? 'pending'),
                ':request_priority' => (string) ($requestData['request_priority'] ?? 'low'),
                ':request_notes' => $requestData['request_notes'] ?? null,
                ':request_submitted_at' => (string) ($requestData['request_submitted_at'] ?? $this->getCurrentIstDateTime()),
            ]);

            $requestId = (int) $this->db->lastInsertId();

            $this->snapshotWorkflowStepsForRequest($requestId, (int) ($requestData['workflow_id'] ?? 0));

                $currentStepId = (int) ($requestData['request_current_step_id'] ?? 0);
                $assignedToUserIds = [];
                if (isset($requestData['request_step_assigned_to_ids']) && is_array($requestData['request_step_assigned_to_ids'])) {
                    foreach ($requestData['request_step_assigned_to_ids'] as $candidateUserId) {
                        $normalizedUserId = (int) $candidateUserId;
                        if ($normalizedUserId > 0) {
                            $assignedToUserIds[] = $normalizedUserId;
                        }
                    }
                }

                if ($assignedToUserIds === []) {
                    $singleAssignedToUserId = (int) ($requestData['request_step_assigned_to'] ?? 0);
                    if ($singleAssignedToUserId > 0) {
                        $assignedToUserIds[] = $singleAssignedToUserId;
                    }
                }

                $assignedToUserIds = array_values(array_unique($assignedToUserIds));

                if ($requestId > 0 && $currentStepId > 0 && $assignedToUserIds !== []) {
                    $assignmentSql = "INSERT INTO request_step_assignments (
                            request_id,
                            workflow_step_id,
                            request_step_assigned_to,
                            request_step_status,
                            request_step_acted_at,
                            request_step_comment
                        ) VALUES (
                            :request_id,
                            :workflow_step_id,
                            :request_step_assigned_to,
                            :request_step_status,
                            :request_step_acted_at,
                            :request_step_comment
                        )";

                    $assignmentStmt = $this->db->prepare($assignmentSql);
                    foreach ($assignedToUserIds as $assignedToUserId) {
                        $assignmentStmt->execute([
                            ':request_id' => $requestId,
                            ':workflow_step_id' => $currentStepId,
                            ':request_step_assigned_to' => $assignedToUserId,
                            ':request_step_status' => 'pending',
                            ':request_step_acted_at' => null,
                            ':request_step_comment' => null,
                        ]);
                    }
                }

            if ($requestId > 0 && is_array($attachmentData) && !empty($attachmentData)) {
                $attachmentSql = "INSERT INTO request_attachments (
                        request_id,
                        attachment_file_name,
                        attachment_stored_name,
                        attachment_file_path,
                        attachment_file_data,
                        attachment_file_size,
                        attachment_mime_type,
                        attachment_type,
                        attachment_uploaded_by
                    ) VALUES (
                        :request_id,
                        :attachment_file_name,
                        :attachment_stored_name,
                        :attachment_file_path,
                        :attachment_file_data,
                        :attachment_file_size,
                        :attachment_mime_type,
                        :attachment_type,
                        :attachment_uploaded_by
                    )";

                $attachmentStmt = $this->db->prepare($attachmentSql);
                $attachmentStmt->execute([
                    ':request_id' => $requestId,
                    ':attachment_file_name' => (string) ($attachmentData['attachment_file_name'] ?? ''),
                    ':attachment_stored_name' => (string) ($attachmentData['attachment_stored_name'] ?? ''),
                    ':attachment_file_path' => (string) ($attachmentData['attachment_file_path'] ?? ''),
                    ':attachment_file_data' => (string) ($attachmentData['attachment_file_data'] ?? ''),
                    ':attachment_file_size' => (int) ($attachmentData['attachment_file_size'] ?? 0),
                    ':attachment_mime_type' => (string) ($attachmentData['attachment_mime_type'] ?? ''),
                    ':attachment_type' => (string) ($attachmentData['attachment_type'] ?? 'other'),
                    ':attachment_uploaded_by' => (int) ($attachmentData['attachment_uploaded_by'] ?? 0),
                ]);
            }

            $this->db->commit();

            return $requestId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('ExpenseModel::createRequest failed: ' . $error->getMessage());

            return 0;
        }
    }

    public function getRequestById(int $requestId): ?array
    {
        $sql = "SELECT r.*, d.department_name, u.user_name AS submitter_name, u.user_email AS submitter_email
                FROM requests r
                LEFT JOIN departments d ON r.department_id = d.id
                LEFT JOIN users u ON r.request_submitted_by = u.user_id
                WHERE r.request_id = :request_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':request_id' => $requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function getAttachmentsByRequestId(int $requestId): array
    {
        $sql = "SELECT
                    attachment_id,
                    request_id,
                    attachment_file_name,
                    attachment_stored_name,
                    attachment_file_path,
                    attachment_file_size,
                    attachment_mime_type,
                    attachment_type,
                    attachment_uploaded_by,
                    attachment_uploaded_at
                FROM request_attachments
                WHERE request_id = :request_id
                ORDER BY attachment_id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':request_id' => $requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAttachmentById(int $attachmentId, int $requestId): ?array
    {
        $sql = "SELECT
                    attachment_id,
                    request_id,
                    attachment_file_name,
                    attachment_stored_name,
                    attachment_file_path,
                    attachment_file_data,
                    attachment_file_size,
                    attachment_mime_type,
                    attachment_type,
                    attachment_uploaded_by,
                    attachment_uploaded_at
                FROM request_attachments
                WHERE attachment_id = :attachment_id
                  AND request_id = :request_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':attachment_id' => $attachmentId,
            ':request_id' => $requestId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function getRequestReviewDetails(int $requestId): ?array
    {
        $sql = "SELECT
                    r.*,
                    d.department_name,
                    bc.budget_category_name,
                    bc.budget_category_type,
                    w.workflow_name,
                    w.workflow_type,
                    u.user_name AS submitter_name,
                    u.user_email AS submitter_email
                FROM requests r
                LEFT JOIN departments d ON d.id = r.department_id
                LEFT JOIN budget_categories bc ON bc.budget_category_id = r.budget_category_id
                LEFT JOIN workflows w ON w.workflow_id = r.workflow_id
                LEFT JOIN users u ON u.user_id = r.request_submitted_by
                WHERE r.request_id = :request_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':request_id' => $requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function getRequestActionHistory(int $requestId): array
    {
        $sql = "SELECT
                    ra.action_id,
                    ra.request_id,
                    ra.workflow_step_id,
                    ra.action,
                    ra.acted_at,
                    ra.action_actor_id,
                    ra.action_reassigned_to,
                    ra.action_comment,
                    COALESCE(rsw.step_order, ws.step_order) AS step_order,
                    COALESCE(rsw.step_name, ws.step_name) AS step_name,
                    actor.user_name AS actor_name,
                    reassigned.user_name AS reassigned_to_name
                FROM request_actions ra
                LEFT JOIN request_workflow_steps rsw
                    ON rsw.request_id = ra.request_id
                   AND rsw.workflow_step_id = ra.workflow_step_id
                LEFT JOIN workflow_steps ws ON ws.step_id = ra.workflow_step_id
                LEFT JOIN users actor ON actor.user_id = ra.action_actor_id
                LEFT JOIN users reassigned ON reassigned.user_id = ra.action_reassigned_to
                WHERE ra.request_id = :request_id
                ORDER BY ra.acted_at ASC, ra.action_id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':request_id' => $requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getWorkflowProgressForRequest(int $requestId, int $workflowId): array
    {
        $snapshotSteps = $this->getRequestWorkflowSteps($requestId);
        if ($snapshotSteps !== []) {
            $sql = "SELECT
                    rsw.workflow_step_id AS step_id,
                        rsw.step_order,
                        rsw.step_name,
                        rsw.step_approver_type,
                        rsw.step_approver_role,
                        rsw.step_is_required,
                        rsw.step_timeout_hours,
                        rsa.request_step_status,
                        rsa.request_step_acted_at,
                        rsa.request_step_comment,
                        assignee.user_name AS assigned_to_name,
                        la.action AS latest_action,
                        la.acted_at AS latest_action_at,
                        la.action_comment AS latest_action_comment,
                        actor.user_name AS latest_action_actor_name
                    FROM request_workflow_steps rsw
                    LEFT JOIN (
                        SELECT rsa1.*
                        FROM request_step_assignments rsa1
                        INNER JOIN (
                            SELECT workflow_step_id, MAX(request_step_id) AS latest_step_assignment_id
                            FROM request_step_assignments
                            WHERE request_id = :request_id_for_assignment
                            GROUP BY workflow_step_id
                        ) latest_rsa
                            ON latest_rsa.latest_step_assignment_id = rsa1.request_step_id
                    ) rsa ON rsa.workflow_step_id = rsw.workflow_step_id
                    LEFT JOIN users assignee ON assignee.user_id = rsa.request_step_assigned_to
                    LEFT JOIN (
                        SELECT ra1.*
                        FROM request_actions ra1
                        INNER JOIN (
                            SELECT workflow_step_id, MAX(action_id) AS latest_action_id
                            FROM request_actions
                            WHERE request_id = :request_id_for_actions
                            GROUP BY workflow_step_id
                        ) latest_ra
                            ON latest_ra.latest_action_id = ra1.action_id
                    ) la ON la.workflow_step_id = rsw.workflow_step_id
                    LEFT JOIN users actor ON actor.user_id = la.action_actor_id
                    WHERE rsw.request_id = :request_id
                    ORDER BY rsw.step_order ASC, rsw.workflow_step_id ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':request_id_for_assignment' => $requestId,
                ':request_id_for_actions' => $requestId,
                ':request_id' => $requestId,
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $sql = "SELECT
                    ws.step_id,
                    ws.step_order,
                    ws.step_name,
                    ws.step_approver_type,
                    ws.step_approver_role,
                    ws.step_is_required,
                    ws.step_timeout_hours,
                    rsa.request_step_status,
                    rsa.request_step_acted_at,
                    rsa.request_step_comment,
                    assignee.user_name AS assigned_to_name,
                    la.action AS latest_action,
                    la.acted_at AS latest_action_at,
                    la.action_comment AS latest_action_comment,
                    actor.user_name AS latest_action_actor_name
                FROM workflow_steps ws
                LEFT JOIN (
                    SELECT rsa1.*
                    FROM request_step_assignments rsa1
                    INNER JOIN (
                        SELECT workflow_step_id, MAX(request_step_id) AS latest_step_assignment_id
                        FROM request_step_assignments
                        WHERE request_id = :request_id_for_assignment
                        GROUP BY workflow_step_id
                    ) latest_rsa
                        ON latest_rsa.latest_step_assignment_id = rsa1.request_step_id
                ) rsa ON rsa.workflow_step_id = ws.step_id
                LEFT JOIN users assignee ON assignee.user_id = rsa.request_step_assigned_to
                LEFT JOIN (
                    SELECT ra1.*
                    FROM request_actions ra1
                    INNER JOIN (
                        SELECT workflow_step_id, MAX(action_id) AS latest_action_id
                        FROM request_actions
                        WHERE request_id = :request_id_for_actions
                        GROUP BY workflow_step_id
                    ) latest_ra
                        ON latest_ra.latest_action_id = ra1.action_id
                ) la ON la.workflow_step_id = ws.step_id
                LEFT JOIN users actor ON actor.user_id = la.action_actor_id
                WHERE ws.workflow_id = :workflow_id
                ORDER BY ws.step_order ASC, ws.step_id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':request_id_for_assignment' => $requestId,
            ':request_id_for_actions' => $requestId,
            ':workflow_id' => $workflowId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isUserAssignedToRequest(int $requestId, int $userId): bool
    {
        if ($requestId <= 0 || $userId <= 0) {
            return false;
        }

        $sql = "SELECT 1
                FROM request_step_assignments
                WHERE request_id = :request_id
                  AND request_step_assigned_to = :user_id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':request_id' => $requestId,
            ':user_id' => $userId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function getPendingAssignmentForUser(int $requestId, int $userId): ?array
    {
        if ($requestId <= 0 || $userId <= 0) {
            return null;
        }

        $sql = "SELECT
                    r.request_id,
                    r.workflow_id,
                    r.department_id,
                    r.budget_category_id,
                    r.request_amount,
                    r.request_currency,
                    r.request_status,
                    r.request_current_step_id,
                    r.request_submitted_by,
                    r.request_submitted_at,
                    r.request_resolved_at,
                    rsa.request_step_id,
                    rsa.workflow_step_id,
                    rsa.request_step_assigned_to,
                    rsa.request_step_status,
                    rsa.request_step_acted_at,
                    rsa.request_step_comment
                FROM requests r
                INNER JOIN request_step_assignments rsa ON rsa.request_id = r.request_id
                    AND rsa.workflow_step_id = r.request_current_step_id
                    AND rsa.request_step_assigned_to = :user_id
                    AND rsa.request_step_status = 'pending'
                WHERE r.request_id = :request_id
                  AND r.request_status = 'pending'
                ORDER BY rsa.request_step_id DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':request_id' => $requestId,
            ':user_id' => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function getCurrentStepApproverNotifications(int $requestId): array
    {
        if ($requestId <= 0) {
            return [];
        }

        $sql = "SELECT
                    u.user_id AS approver_id,
                    u.user_name AS approver_name,
                    u.user_email AS approver_email,
                    MAX(COALESCE(rsw.step_timeout_hours, ws.step_timeout_hours, 24)) AS approval_timeout
                FROM requests r
                INNER JOIN request_step_assignments rsa
                    ON rsa.request_id = r.request_id
                   AND rsa.workflow_step_id = r.request_current_step_id
                   AND rsa.request_step_status = 'pending'
                INNER JOIN users u
                    ON u.user_id = rsa.request_step_assigned_to
                LEFT JOIN request_workflow_steps rsw
                    ON rsw.request_id = r.request_id
                   AND rsw.workflow_step_id = r.request_current_step_id
                LEFT JOIN workflow_steps ws
                    ON ws.step_id = r.request_current_step_id
                WHERE r.request_id = :request_id
                  AND r.request_status = 'pending'
                                GROUP BY u.user_id, u.user_name, u.user_email
                ORDER BY u.user_name ASC, u.user_id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':request_id' => $requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLatestRequestActionActorName(int $requestId): ?string
    {
        if ($requestId <= 0) {
            return null;
        }

        $sql = "SELECT u.user_name
                FROM request_actions ra
                INNER JOIN users u ON u.user_id = ra.action_actor_id
                WHERE ra.request_id = :request_id
                ORDER BY ra.action_id DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':request_id' => $requestId]);
        $name = $stmt->fetchColumn();

        if ($name === false) {
            return null;
        }

        return trim((string) $name);
    }

    public function getLatestRequestActionStepTitle(int $requestId, ?string $action = null): ?string
    {
        if ($requestId <= 0) {
            return null;
        }

        $sql = "SELECT COALESCE(NULLIF(TRIM(rsw.step_name), ''), NULLIF(TRIM(ws.step_name), '')) AS step_title
                FROM request_actions ra
                LEFT JOIN request_workflow_steps rsw
                    ON rsw.request_id = ra.request_id
                   AND rsw.workflow_step_id = ra.workflow_step_id
                LEFT JOIN workflow_steps ws ON ws.step_id = ra.workflow_step_id
                WHERE ra.request_id = :request_id";

        $params = [':request_id' => $requestId];
        if ($action !== null && trim($action) !== '') {
            $sql .= ' AND ra.action = :action';
            $params[':action'] = strtolower(trim($action));
        }

        $sql .= ' ORDER BY ra.action_id DESC LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stepTitle = $stmt->fetchColumn();

        if ($stepTitle === false) {
            return null;
        }

        $stepTitle = trim((string) $stepTitle);

        return $stepTitle !== '' ? $stepTitle : null;
    }

    public function getDepartmentUsersForReassignment(int $departmentId, int $excludeUserId = 0): array
    {
        if ($departmentId <= 0) {
            return [];
        }

        $sql = "SELECT user_id, user_name, user_email, user_role
                FROM users
                WHERE user_is_active = 1
                  AND department_id = :department_id";

        $params = [':department_id' => $departmentId];
        if ($excludeUserId > 0) {
            $sql .= ' AND user_id <> :exclude_user_id';
            $params[':exclude_user_id'] = $excludeUserId;
        }

        $sql .= ' ORDER BY user_name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function processRequestAction(int $requestId, int $actorUserId, string $action, ?string $comment = null, ?int $reassignTo = null): array
    {
        if ($requestId <= 0 || $actorUserId <= 0) {
            throw new RuntimeException('Invalid request action context.');
        }

        $action = strtolower(trim($action));
        if (!in_array($action, ['approve', 'reject', 'reassign'], true)) {
            throw new RuntimeException('Invalid request action.');
        }

        $request = $this->getRequestById($requestId);
        if (!is_array($request)) {
            throw new RuntimeException('Request not found.');
        }

        $pendingAssignment = $this->getPendingAssignmentForUser($requestId, $actorUserId);
        if (!is_array($pendingAssignment)) {
            throw new RuntimeException('You are not assigned to this request step.');
        }

        $workflowId = (int) ($request['workflow_id'] ?? 0);
        $currentStepId = (int) ($request['request_current_step_id'] ?? 0);
        $requestStatus = strtolower(trim((string) ($request['request_status'] ?? 'pending')));
        if ($workflowId <= 0 || $currentStepId <= 0 || $requestStatus !== 'pending') {
            throw new RuntimeException('This request is no longer available for action.');
        }

            $currentStep = $this->getCurrentRequestStepDefinition($requestId, $currentStepId, $workflowId);
        if (!is_array($currentStep)) {
            throw new RuntimeException('Current workflow step was not found.');
        }

        $this->db->beginTransaction();

        try {
            $actedAt = $this->getCurrentIstDateTime();
            $commentValue = trim((string) ($comment ?? ''));
            $departmentId = (int) ($request['department_id'] ?? 0);
            $requesterId = (int) ($request['request_submitted_by'] ?? 0);

            if ($action === 'reassign') {
                if ($reassignTo === null || $reassignTo <= 0) {
                    throw new RuntimeException('Please select a user to reassign to.');
                }

                $reassignableUsers = $this->getDepartmentUsersForReassignment($departmentId, $actorUserId);
                $isAllowedTarget = false;
                foreach ($reassignableUsers as $candidate) {
                    if ((int) ($candidate['user_id'] ?? 0) === $reassignTo) {
                        $isAllowedTarget = true;
                        break;
                    }
                }

                if (!$isAllowedTarget) {
                    throw new RuntimeException('Selected reassignment target is not valid for this department.');
                }

                $updateAssignmentSql = "UPDATE request_step_assignments
                                        SET request_step_assigned_to = :request_step_assigned_to
                                        WHERE request_id = :request_id
                                          AND workflow_step_id = :workflow_step_id
                                          AND request_step_assigned_to = :current_assignee
                                          AND request_step_status = 'pending'";
                $updateAssignmentStmt = $this->db->prepare($updateAssignmentSql);
                $updateAssignmentStmt->execute([
                    ':request_step_assigned_to' => $reassignTo,
                    ':request_id' => $requestId,
                    ':workflow_step_id' => $currentStepId,
                    ':current_assignee' => $actorUserId,
                ]);

                if ($updateAssignmentStmt->rowCount() <= 0) {
                    throw new RuntimeException('Unable to reassign the current approval step.');
                }

                $this->insertRequestAction($requestId, $currentStepId, 'reassign', $actorUserId, $reassignTo, $commentValue !== '' ? $commentValue : null, $actedAt);
                $this->db->commit();

                return [
                    'message' => 'Request reassigned successfully.',
                    'action' => 'reassign',
                ];
            }

            $updateStepSql = "UPDATE request_step_assignments
                              SET request_step_status = :request_step_status,
                                  request_step_acted_at = :request_step_acted_at,
                                  request_step_comment = :request_step_comment
                              WHERE request_id = :request_id
                                AND workflow_step_id = :workflow_step_id
                                AND request_step_assigned_to = :request_step_assigned_to
                                AND request_step_status = 'pending'";
            $updateStepStmt = $this->db->prepare($updateStepSql);

            if ($action === 'reject') {
                if ($commentValue === '') {
                    throw new RuntimeException('A description is required when rejecting a request.');
                }

                $updateStepStmt->execute([
                    ':request_step_status' => 'rejected',
                    ':request_step_acted_at' => $actedAt,
                    ':request_step_comment' => $commentValue,
                    ':request_id' => $requestId,
                    ':workflow_step_id' => $currentStepId,
                    ':request_step_assigned_to' => $actorUserId,
                ]);

                if ($updateStepStmt->rowCount() <= 0) {
                    throw new RuntimeException('Unable to reject the current approval step.');
                }

                $requestUpdateStmt = $this->db->prepare("UPDATE requests
                                                         SET request_status = 'rejected',
                                                             request_resolved_at = :request_resolved_at
                                                         WHERE request_id = :request_id");
                $requestUpdateStmt->execute([
                    ':request_id' => $requestId,
                    ':request_resolved_at' => $actedAt,
                ]);

                $this->insertRequestAction($requestId, $currentStepId, 'reject', $actorUserId, null, $commentValue, $actedAt);
                $this->db->commit();

                return [
                    'message' => 'Request rejected successfully.',
                    'action' => 'reject',
                ];
            }

            $updateStepStmt->execute([
                ':request_step_status' => 'approved',
                ':request_step_acted_at' => $actedAt,
                ':request_step_comment' => $commentValue !== '' ? $commentValue : null,
                ':request_id' => $requestId,
                ':workflow_step_id' => $currentStepId,
                ':request_step_assigned_to' => $actorUserId,
            ]);

            if ($updateStepStmt->rowCount() <= 0) {
                throw new RuntimeException('Unable to approve the current workflow step.');
            }

            $nextStep = $this->getNextRequestWorkflowStep($requestId, $currentStepId);
            if (!is_array($nextStep)) {
                $nextStep = $this->getNextWorkflowStep((int) $workflowId, (int) ($currentStep['step_order'] ?? 0));
            }
            if (is_array($nextStep)) {
                $nextAssigneeIds = $this->resolveStepAssigneeIdsForRequestStep($nextStep, $departmentId, $requesterId);
                if ($nextAssigneeIds === []) {
                    throw new RuntimeException('The next workflow step does not have an approver user.');
                }

                $insertNextAssignmentSql = "INSERT INTO request_step_assignments (
                        request_id,
                        workflow_step_id,
                        request_step_assigned_to,
                        request_step_status,
                        request_step_acted_at,
                        request_step_comment
                    ) VALUES (
                        :request_id,
                        :workflow_step_id,
                        :request_step_assigned_to,
                        'pending',
                        NULL,
                        NULL
                    )";
                $insertNextAssignmentStmt = $this->db->prepare($insertNextAssignmentSql);
                $createdAssignments = 0;
                foreach ($nextAssigneeIds as $nextAssigneeId) {
                    $insertNextAssignmentStmt->execute([
                        ':request_id' => $requestId,
                        ':workflow_step_id' => (int) ($nextStep['step_id'] ?? 0),
                        ':request_step_assigned_to' => $nextAssigneeId,
                    ]);
                    $createdAssignments += $insertNextAssignmentStmt->rowCount();
                }

                if ($createdAssignments <= 0) {
                    throw new RuntimeException('Unable to move the request to the next approval step.');
                }

                $requestUpdateStmt = $this->db->prepare("UPDATE requests
                                                         SET request_current_step_id = :request_current_step_id
                                                         WHERE request_id = :request_id");
                $requestUpdateStmt->execute([
                    ':request_id' => $requestId,
                    ':request_current_step_id' => (int) ($nextStep['step_id'] ?? 0),
                ]);
            } else {
                $this->adjustBudgetForFinalApproval($request);

                $requestUpdateStmt = $this->db->prepare("UPDATE requests
                                                         SET request_status = 'approved',
                                                             request_current_step_id = NULL,
                                                             request_resolved_at = :request_resolved_at
                                                         WHERE request_id = :request_id");
                $requestUpdateStmt->execute([
                    ':request_id' => $requestId,
                    ':request_resolved_at' => $actedAt,
                ]);
            }

            $this->insertRequestAction($requestId, $currentStepId, 'approve', $actorUserId, null, $commentValue !== '' ? $commentValue : null, $actedAt);
            $this->db->commit();

            return [
                'message' => $nextStep !== null ? 'Request approved and moved to the next step.' : 'Request approved successfully.',
                'action' => 'approve',
            ];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $error;
        }
    }

    private function insertRequestAction(int $requestId, int $workflowStepId, string $action, int $actorUserId, ?int $reassignTo, ?string $comment, string $actedAt): void
    {
        $sql = "INSERT INTO request_actions (
                    request_id,
                    workflow_step_id,
                    action,
                    acted_at,
                    action_actor_id,
                    action_reassigned_to,
                    action_comment
                ) VALUES (
                    :request_id,
                    :workflow_step_id,
                    :action,
                    :acted_at,
                    :action_actor_id,
                    :action_reassigned_to,
                    :action_comment
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':request_id' => $requestId,
            ':workflow_step_id' => $workflowStepId > 0 ? $workflowStepId : null,
            ':action' => $action,
            ':acted_at' => $actedAt,
            ':action_actor_id' => $actorUserId,
            ':action_reassigned_to' => $reassignTo,
            ':action_comment' => $comment,
        ]);
    }

    private function getWorkflowStepById(int $stepId): ?array
    {
        if ($stepId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT step_id, workflow_id, step_order, step_name, step_approver_type, step_approver_role, step_approver_user_id
             FROM workflow_steps
             WHERE step_id = :step_id
             LIMIT 1"
        );
        $stmt->execute([':step_id' => $stepId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private function getNextWorkflowStep(int $workflowId, int $currentStepOrder): ?array
    {
        if ($workflowId <= 0 || $currentStepOrder <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT step_id, workflow_id, step_order, step_name, step_approver_type, step_approver_role, step_approver_user_id
             FROM workflow_steps
             WHERE workflow_id = :workflow_id
               AND step_order > :current_step_order
             ORDER BY step_order ASC, step_id ASC
             LIMIT 1"
        );
        $stmt->execute([
            ':workflow_id' => $workflowId,
            ':current_step_order' => $currentStepOrder,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private function resolveStepAssigneeIdsForRequestStep(array $step, int $departmentId, int $requesterId): array
    {
        $explicitUserId = (int) ($step['step_approver_user_id'] ?? 0);
        if ($explicitUserId > 0) {
            return [$explicitUserId];
        }

        $approverRole = strtolower(trim((string) ($step['step_approver_role'] ?? '')));
        $approverType = strtolower(trim((string) ($step['step_approver_type'] ?? '')));
        if ($approverType === 'manager') {
            $managerAssigneeId = $this->findManagerAssigneeForDepartment($requesterId, $departmentId);
            return $managerAssigneeId !== null && $managerAssigneeId > 0 ? [$managerAssigneeId] : [];
        }

        if ($approverType === 'department_head') {
            $departmentHeadId = $this->findDepartmentHeadUserId($departmentId);
            return $departmentHeadId !== null && $departmentHeadId > 0 ? [$departmentHeadId] : [];
        }

        if ($approverRole === '') {
            if ($approverType === 'manager') {
                $approverRole = 'manager';
            } elseif ($approverType === 'department_head') {
                $approverRole = 'department_head';
            }
        }

        if ($approverRole === '') {
            return [];
        }

        $workflowModel = new WorkflowModel();
        $assigneeIds = [];
        foreach ($workflowModel->getActiveUsers() as $user) {
            if (strtolower(trim((string) ($user['approver_role'] ?? ''))) === $approverRole) {
                $userId = (int) ($user['user_id'] ?? 0);
                if ($userId > 0) {
                    $assigneeIds[] = $userId;
                }
            }
        }

        return array_values(array_unique($assigneeIds));
    }

    private function deriveFiscalScope(?string $referenceDate = null): array
    {
        $date = $referenceDate !== null && trim($referenceDate) !== ''
            ? new DateTime($referenceDate, new DateTimeZone('Asia/Kolkata'))
            : new DateTime('now', new DateTimeZone('Asia/Kolkata'));

        $month = (int) $date->format('n');
        $quarter = (int) ceil($month / 3);

        return [
            'year' => $date->format('Y'),
            'period' => 'Q' . $quarter,
        ];
    }

    private function adjustBudgetForFinalApproval(array $request): void
    {
        $departmentId = (int) ($request['department_id'] ?? 0);
        $budgetCategoryId = (int) ($request['budget_category_id'] ?? 0);
        $requestAmount = (float) ($request['request_amount'] ?? 0);
        if ($departmentId <= 0 || $budgetCategoryId <= 0 || $requestAmount <= 0) {
            throw new RuntimeException('Unable to adjust the department budget for this request.');
        }

        $scope = $this->deriveFiscalScope((string) ($request['request_submitted_at'] ?? ''));
        $budgetModel = new BudgetModel();
        $budgetRow = $budgetModel->findExistingBudgetByScope($departmentId, $budgetCategoryId, $scope['year'], $scope['period']);
        if (!is_array($budgetRow) || (int) ($budgetRow['budget_id'] ?? 0) <= 0) {
            throw new RuntimeException('No matching budget record was found for this request.');
        }

        $fullBudget = $budgetModel->getBudgetById((int) $budgetRow['budget_id']);
        if (!is_array($fullBudget)) {
            throw new RuntimeException('Unable to load the matching budget record.');
        }

        $currentAmount = (float) ($fullBudget['budget_allocated_amount'] ?? 0);
        $remainingAmount = round($currentAmount - $requestAmount, 2);
        if ($remainingAmount < 0) {
            throw new RuntimeException('The request amount exceeds the remaining budget allocation.');
        }

        $fullBudget['budget_allocated_amount'] = $remainingAmount;
        $updated = $budgetModel->updateBudget((int) $fullBudget['budget_id'], $fullBudget);
        if (!$updated) {
            throw new RuntimeException('Unable to update the department budget.');
        }
    }
}