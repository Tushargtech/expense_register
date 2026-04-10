<?php

class ExpenseModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    private function getCurrentIstDateTime(): string
    {
        $istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        return $istNow->format('Y-m-d H:i:s');
    }

    private function buildFilterSql(array $filters): array
    {
        $whereSql = " WHERE r.request_type = 'expense'";
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $whereSql .= ' AND ('
                . 'r.request_reference_no LIKE :search '
                . 'OR r.request_title LIKE :search '
                . 'OR r.request_category LIKE :search '
                . 'OR bc.budget_category_name LIKE :search '
                . 'OR CAST(r.request_id AS CHAR) LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $whereSql .= ' AND r.request_status = :status';
            $params[':status'] = $status;
        }

        $departmentId = (int) ($filters['department_id'] ?? 0);
        if ($departmentId > 0) {
            $whereSql .= ' AND r.department_id = :department_id';
            $params[':department_id'] = $departmentId;
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $whereSql .= ' AND r.request_submitted_at >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $whereSql .= ' AND r.request_submitted_at <= :date_to';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }

        return [$whereSql, $params];
    }

    public function getAllExpenseRequests(array $filters = [], int $limit = 10, int $offset = 0): array
    {
        [$whereSql, $params] = $this->buildFilterSql($filters);

        $sql = "SELECT
                r.request_id,
                r.request_reference_no,
                r.request_title,
                r.request_amount,
                r.request_currency,
                r.request_priority,
                r.request_status,
                r.request_submitted_at,
                r.request_category,
                u.user_name AS submitted_by_name,
                d.department_name,
                bc.budget_category_name AS request_category_name
            FROM requests r
            LEFT JOIN users u ON r.request_submitted_by = u.user_id
            LEFT JOIN departments d ON r.department_id = d.id
            LEFT JOIN budget_categories bc ON r.budget_category_id = bc.budget_category_id"
            . $whereSql .
            ' ORDER BY r.request_id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countAllExpenseRequests(array $filters = []): int
    {
        [$whereSql, $params] = $this->buildFilterSql($filters);

        $sql = 'SELECT COUNT(*) FROM requests r
            LEFT JOIN budget_categories bc ON r.budget_category_id = bc.budget_category_id' . $whereSql;

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function getRequestById(int $requestId): ?array
    {
        $sql = "SELECT
                r.request_id,
                r.request_reference_no,
                r.request_title,
                r.request_description,
                r.request_amount,
                r.request_currency,
                r.department_id,
                r.request_category,
                r.budget_category_id,
                r.workflow_id,
                r.request_current_step_id,
                r.request_submitted_by,
                r.request_status,
                r.request_priority,
                r.request_notes,
                r.request_submitted_at,
                r.request_resolved_at,
                u.user_name AS submitted_by_name,
                d.department_name,
                bc.budget_category_name AS budget_category_name
            FROM requests r
            LEFT JOIN users u ON r.request_submitted_by = u.user_id
            LEFT JOIN departments d ON r.department_id = d.id
            LEFT JOIN budget_categories bc ON r.budget_category_id = bc.budget_category_id
            WHERE r.request_id = :request_id AND r.request_type = 'expense'
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':request_id' => $requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    public function getRequestAttachments(int $requestId): array
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
            ORDER BY attachment_uploaded_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':request_id' => $requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRequestSteps(int $requestId): array
    {
        $sql = "SELECT
                rsa.request_step_id,
                rsa.request_id,
                rsa.workflow_step_id,
                rsa.request_step_assigned_to,
                rsa.request_step_status,
                rsa.request_step_acted_at,
                rsa.request_step_comment,
                ws.step_name,
                ws.step_order,
                u.user_name AS assigned_to_name
            FROM request_step_assignments rsa
            LEFT JOIN workflow_steps ws ON rsa.workflow_step_id = ws.step_id
            LEFT JOIN users u ON rsa.request_step_assigned_to = u.user_id
            WHERE rsa.request_id = :request_id
            ORDER BY ws.step_order ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':request_id' => $requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRequestActions(int $requestId): array
    {
        $sql = "SELECT
                action_id,
                request_id,
                workflow_step_id,
                action,
                acted_at,
                action_actor_id,
                action_reassigned_to,
                action_comment
            FROM request_actions
            WHERE request_id = :request_id
            ORDER BY acted_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':request_id' => $requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPendingApprovalRequests(int $assignedUserId): array
    {
        $sql = "SELECT
                r.request_id,
                r.request_reference_no,
                r.request_title,
                r.request_amount,
                r.request_currency,
                r.request_priority,
                r.request_status,
                r.request_submitted_at,
                r.request_category,
                u.user_name AS submitted_by_name,
                d.department_name,
                bc.budget_category_name AS request_category_name
            FROM requests r
            INNER JOIN request_step_assignments rsa ON rsa.request_id = r.request_id
            LEFT JOIN users u ON r.request_submitted_by = u.user_id
            LEFT JOIN departments d ON r.department_id = d.id
            LEFT JOIN budget_categories bc ON r.budget_category_id = bc.budget_category_id
            WHERE r.request_status = 'pending'
              AND rsa.request_step_status = 'pending'
              AND rsa.request_step_assigned_to = :assigned_user_id
              AND r.request_current_step_id = rsa.workflow_step_id
              AND r.request_submitted_by != :assigned_user_id
            ORDER BY r.request_submitted_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':assigned_user_id' => $assignedUserId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createExpenseRequest(array $requestData, array $fileUpload, int $submittedBy): bool
    {
        $workflowId = $this->getDefaultWorkflowId();
        if ($workflowId === null) {
            return false;
        }

        $requestCategory = $this->getBudgetCategoryName($requestData['budget_category_id']);
        $requestReferenceNo = $this->generateRequestReference();

        // Get workflow steps
        $workflowSteps = $this->getWorkflowSteps($workflowId);
        if (empty($workflowSteps)) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO requests (
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
                    :reference_no,
                    :type,
                    :title,
                    :description,
                    :amount,
                    :currency,
                    :department_id,
                    :category,
                    :budget_category_id,
                    :workflow_id,
                    :current_step_id,
                    :submitted_by,
                    :status,
                    :priority,
                    :notes,
                    :submitted_at
                )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':reference_no' => $requestReferenceNo,
                ':type' => 'expense',
                ':title' => $requestData['request_title'],
                ':description' => $requestData['request_description'],
                ':amount' => $requestData['request_amount'],
                ':currency' => $requestData['request_currency'],
                ':department_id' => $requestData['department_id'],
                ':category' => $requestCategory,
                ':budget_category_id' => $requestData['budget_category_id'],
                ':workflow_id' => $workflowId,
                ':current_step_id' => $workflowSteps[0]['step_id'], // First step
                ':submitted_by' => $submittedBy,
                ':status' => 'pending',
                ':priority' => $requestData['request_priority'],
                ':notes' => $requestData['request_notes'],
                ':submitted_at' => $this->getCurrentIstDateTime(),
            ]);

            $requestId = (int) $this->db->lastInsertId();

            // Create step assignments
            $this->createRequestStepAssignments($requestId, $workflowSteps, $requestData['department_id']);

            if (!empty($fileUpload) && $fileUpload['error'] === UPLOAD_ERR_OK) {
                $attachmentInfo = $this->processAttachmentFile($fileUpload, $submittedBy);
                if ($attachmentInfo !== null) {
                    $this->addRequestAttachment($requestId, $attachmentInfo);
                }
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

    private function getWorkflowSteps(int $workflowId): array
    {
        $stmt = $this->db->prepare(
            "SELECT step_id, step_order, step_name, step_approver_type, step_approver_role, step_approver_user_id
             FROM workflow_steps
             WHERE workflow_id = :workflow_id
             ORDER BY step_order ASC"
        );
        $stmt->execute([':workflow_id' => $workflowId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function createRequestStepAssignments(int $requestId, array $workflowSteps, int $departmentId): void
    {
        $sql = "INSERT INTO request_step_assignments (
                request_id,
                workflow_step_id,
                request_step_assigned_to,
                request_step_status
            ) VALUES (
                :request_id,
                :workflow_step_id,
                :assigned_to,
                'pending'
            )";

        $stmt = $this->db->prepare($sql);

        foreach ($workflowSteps as $step) {
            $assignedTo = $this->determineStepAssignee($step, $departmentId);
            if ($assignedTo !== null) {
                $stmt->execute([
                    ':request_id' => $requestId,
                    ':workflow_step_id' => $step['step_id'],
                    ':assigned_to' => $assignedTo,
                ]);
            }
        }
    }

    private function getNextWorkflowStep(int $workflowId, int $currentStepId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT step_id, step_order
             FROM workflow_steps
             WHERE workflow_id = :workflow_id AND step_order > (
                 SELECT step_order FROM workflow_steps WHERE step_id = :current_step_id
             )
             ORDER BY step_order ASC
             LIMIT 1"
        );
        $stmt->execute([':workflow_id' => $workflowId, ':current_step_id' => $currentStepId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    private function getBudgetCategoryName(int $categoryId): string
    {
        $stmt = $this->db->prepare(
            'SELECT budget_category_name FROM budget_categories WHERE budget_category_id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $categoryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? (string) ($row['budget_category_name'] ?? '') : '';
    }

    private function generateRequestReference(): string
    {
        return 'REQ' . date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
    }

    private function processAttachmentFile(array $fileUpload, int $uploadedBy): ?array
    {
        if (!isset($fileUpload['tmp_name'], $fileUpload['name'], $fileUpload['size'], $fileUpload['type'])) {
            return null;
        }

        if ($fileUpload['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        $originalName = basename((string) $fileUpload['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            return null;
        }

        $safeName = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $originalName);
        $storedName = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
        $uploadDir = ROOT_PATH . '/uploads/expense_attachments';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return null;
        }

        $destination = $uploadDir . '/' . $storedName;
        if (!move_uploaded_file($fileUpload['tmp_name'], $destination)) {
            return null;
        }

        return [
            'attachment_file_name' => $safeName,
            'attachment_stored_name' => $storedName,
            'attachment_file_path' => 'uploads/expense_attachments/' . $storedName,
            'attachment_file_size' => (int) $fileUpload['size'],
            'attachment_mime_type' => (string) $fileUpload['type'],
            'attachment_type' => 'other',
            'attachment_uploaded_by' => $uploadedBy,
            'attachment_uploaded_at' => $this->getCurrentIstDateTime(),
        ];
    }

    private function addRequestAttachment(int $requestId, array $attachmentInfo): bool
    {
        $sql = "INSERT INTO request_attachments (
                request_id,
                attachment_file_name,
                attachment_stored_name,
                attachment_file_path,
                attachment_file_size,
                attachment_mime_type,
                attachment_type,
                attachment_uploaded_by,
                attachment_uploaded_at
            ) VALUES (
                :request_id,
                :file_name,
                :stored_name,
                :file_path,
                :file_size,
                :mime_type,
                :type,
                :uploaded_by,
                :uploaded_at
            )";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ':request_id' => $requestId,
            ':file_name' => $attachmentInfo['attachment_file_name'],
            ':stored_name' => $attachmentInfo['attachment_stored_name'],
            ':file_path' => $attachmentInfo['attachment_file_path'],
            ':file_size' => $attachmentInfo['attachment_file_size'],
            ':mime_type' => $attachmentInfo['attachment_mime_type'],
            ':type' => $attachmentInfo['attachment_type'],
            ':uploaded_by' => $attachmentInfo['attachment_uploaded_by'],
            ':uploaded_at' => $attachmentInfo['attachment_uploaded_at'],
        ]);
    }

    public function saveRequestAction(int $requestId, string $action, int $actorId, string $comment): bool
    {
        $action = strtolower(trim($action));
        if (!in_array($action, ['approve', 'reject'], true)) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            // Get current request and step
            $request = $this->getRequestById($requestId);
            if ($request === null) {
                return false;
            }

            $currentStepId = $request['request_current_step_id'];
            if ($currentStepId === null) {
                // No workflow, direct action
                $status = $action === 'approve' ? 'approved' : 'rejected';
                $timestamp = $this->getCurrentIstDateTime();

                $updateSql = "UPDATE requests
                    SET request_status = :status,
                        request_resolved_at = :resolved_at
                    WHERE request_id = :request_id";

                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->execute([
                    ':status' => $status,
                    ':resolved_at' => $timestamp,
                    ':request_id' => $requestId,
                ]);

                $insertSql = "INSERT INTO request_actions (
                        request_id,
                        workflow_step_id,
                        action,
                        acted_at,
                        action_actor_id,
                        action_comment
                    ) VALUES (
                        :request_id,
                        NULL,
                        :action,
                        :acted_at,
                        :actor_id,
                        :comment
                    )";

                $insertStmt = $this->db->prepare($insertSql);
                $insertStmt->execute([
                    ':request_id' => $requestId,
                    ':action' => $action,
                    ':acted_at' => $timestamp,
                    ':actor_id' => $actorId,
                    ':comment' => $comment,
                ]);

                $this->db->commit();
                return true;
            }

            // Update current step
            $stepUpdateSql = "UPDATE request_step_assignments
                SET request_step_status = :status,
                    request_step_acted_at = :acted_at,
                    request_step_comment = :comment
                WHERE request_id = :request_id AND workflow_step_id = :step_id";

            $stepStmt = $this->db->prepare($stepUpdateSql);
            $stepStmt->execute([
                ':status' => $action === 'approve' ? 'approved' : 'rejected',
                ':acted_at' => $this->getCurrentIstDateTime(),
                ':comment' => $comment,
                ':request_id' => $requestId,
                ':step_id' => $currentStepId,
            ]);

            // Insert action
            $insertSql = "INSERT INTO request_actions (
                    request_id,
                    workflow_step_id,
                    action,
                    acted_at,
                    action_actor_id,
                    action_comment
                ) VALUES (
                    :request_id,
                    :step_id,
                    :action,
                    :acted_at,
                    :actor_id,
                    :comment
                )";

            $insertStmt = $this->db->prepare($insertSql);
            $insertStmt->execute([
                ':request_id' => $requestId,
                ':step_id' => $currentStepId,
                ':action' => $action,
                ':acted_at' => $this->getCurrentIstDateTime(),
                ':actor_id' => $actorId,
                ':comment' => $comment,
            ]);

            if ($action === 'reject') {
                // Reject the request
                $updateSql = "UPDATE requests
                    SET request_status = 'rejected',
                        request_resolved_at = :resolved_at
                    WHERE request_id = :request_id";

                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->execute([
                    ':resolved_at' => $this->getCurrentIstDateTime(),
                    ':request_id' => $requestId,
                ]);
            } else {
                // Approved, check if more steps
                $nextStep = $this->getNextWorkflowStep($request['workflow_id'], $currentStepId);
                if ($nextStep !== null) {
                    // Move to next step
                    $updateSql = "UPDATE requests
                        SET request_current_step_id = :next_step_id
                        WHERE request_id = :request_id";

                    $updateStmt = $this->db->prepare($updateSql);
                    $updateStmt->execute([
                        ':next_step_id' => $nextStep['step_id'],
                        ':request_id' => $requestId,
                    ]);
                } else {
                    // Last step, approve request
                    $updateSql = "UPDATE requests
                        SET request_status = 'approved',
                            request_resolved_at = :resolved_at,
                            request_current_step_id = NULL
                        WHERE request_id = :request_id";

                    $updateStmt = $this->db->prepare($updateSql);
                    $updateStmt->execute([
                        ':resolved_at' => $this->getCurrentIstDateTime(),
                        ':request_id' => $requestId,
                    ]);
                }
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

    private function getDefaultWorkflowId(): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT workflow_id FROM workflows WHERE workflow_type = 'expense' AND workflow_is_default = 1 LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? (int) $row['workflow_id'] : null;
    }

    private function determineStepAssignee(array $step, int $departmentId): ?int
    {
        // If specific user is assigned
        if (!empty($step['step_approver_user_id'])) {
            return (int) $step['step_approver_user_id'];
        }

        // If role-based assignment
        if (!empty($step['step_approver_role'])) {
            $role = strtolower(trim((string) $step['step_approver_role']));
            $stmt = $this->db->prepare(
                "SELECT user_id FROM users WHERE user_role = :role LIMIT 1"
            );
            $stmt->execute([':role' => $role]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                return (int) $row['user_id'];
            }
        }

        // If department-based assignment (manager of the department)
        if (strtolower(trim((string) $step['step_approver_type'])) === 'department_manager') {
            $stmt = $this->db->prepare(
                "SELECT user_id FROM users WHERE user_department_id = :dept_id AND user_role = 'manager' LIMIT 1"
            );
            $stmt->execute([':dept_id' => $departmentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                return (int) $row['user_id'];
            }
        }

        return null;
    }
}
