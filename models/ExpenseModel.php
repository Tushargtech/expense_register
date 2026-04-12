<?php

class ExpenseModel
{
    private $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    private function generateRequestReferenceNo(): string
    {
        $prefix = 'EXP-' . date('Ymd') . '-';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $referenceNo = $prefix . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            if (!$this->requestReferenceExists($referenceNo)) {
                return $referenceNo;
            }
        }

        return $prefix . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    private function requestReferenceExists(string $referenceNo): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM requests WHERE request_reference_no = :reference_no LIMIT 1');
        $stmt->execute([':reference_no' => $referenceNo]);

        return $stmt->fetchColumn() !== false;
    }

    private function getFirstWorkflowStepId(int $workflowId): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT step_id FROM workflow_steps WHERE workflow_id = :workflow_id ORDER BY step_order ASC, step_id ASC LIMIT 1'
        );
        $stmt->execute([':workflow_id' => $workflowId]);
        $stepId = $stmt->fetchColumn();

        return $stepId !== false ? (int) $stepId : null;
    }

    public function getFiltered($search = '', $status = '', $deptId = 0, $dateFrom = '', $dateTo = '', $page = 1, $limit = 10, $submittedBy = 0)
    {
        $where = [];
        $params = [];

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $where[] = "(r.request_id LIKE ? OR r.request_reference_no LIKE ? OR r.request_title LIKE ? OR r.request_category LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($status)) {
            $where[] = "r.request_status = ?";
            $params[] = $status;
        }

        if ($deptId > 0) {
            $where[] = "r.department_id = ?";
            $params[] = $deptId;
        }

        if ((int) $submittedBy > 0) {
            $where[] = "r.request_submitted_by = ?";
            $params[] = (int) $submittedBy;
        }

        if (!empty($dateFrom)) {
            $where[] = "DATE(r.request_submitted_at) >= ?";
            $params[] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $where[] = "DATE(r.request_submitted_at) <= ?";
            $params[] = $dateTo;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $countQuery = "SELECT COUNT(*) as cnt FROM requests r $whereClause";
        $stmt = $this->db->prepare($countQuery);
        $stmt->execute($params);
        $total = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        $offset = max(0, ((int) $page - 1) * (int) $limit);
        $query = "
            SELECT 
                r.request_id,
                r.request_reference_no,
                r.request_type,
                r.request_title,
                r.request_category,
                r.request_amount,
                r.request_currency,
                r.request_status,
                r.request_priority,
                r.request_submitted_at,
                r.request_submitted_by,
                r.department_id,
                u.user_name as submitted_by_name,
                d.department_name
            FROM requests r
            LEFT JOIN users u ON r.request_submitted_by = u.user_id
            LEFT JOIN departments d ON r.department_id = d.id
            $whereClause
            ORDER BY r.request_submitted_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->db->prepare($query);
        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, max(1, (int) $limit), PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'records' => $records,
            'total' => $total,
        ];
    }

    private function insertAttachment(int $requestId, array $attachmentData, int $uploadedBy): void
    {
        $sql = 'INSERT INTO request_attachments (
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
        )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':request_id' => $requestId,
            ':attachment_file_name' => (string) ($attachmentData['attachment_file_name'] ?? ''),
            ':attachment_stored_name' => (string) ($attachmentData['attachment_stored_name'] ?? ''),
            ':attachment_file_path' => (string) ($attachmentData['attachment_file_path'] ?? ''),
            ':attachment_file_data' => (string) ($attachmentData['attachment_file_data'] ?? ''),
            ':attachment_file_size' => (int) ($attachmentData['attachment_file_size'] ?? 0),
            ':attachment_mime_type' => (string) ($attachmentData['attachment_mime_type'] ?? 'application/octet-stream'),
            ':attachment_type' => (string) ($attachmentData['attachment_type'] ?? 'other'),
            ':attachment_uploaded_by' => $uploadedBy,
        ]);
    }

    public function getRequestDetailsById(int $requestId): ?array
    {
        $sql = 'SELECT
                r.request_id,
                r.request_reference_no,
                r.request_type,
                r.request_title,
                r.request_description,
                r.request_amount,
                r.request_currency,
                r.department_id,
                d.department_name,
                r.request_category,
                r.budget_category_id,
                bc.budget_category_name,
                r.workflow_id,
                w.workflow_name,
                r.request_current_step_id,
                r.request_submitted_by,
                u.user_name AS submitted_by_name,
                r.request_status,
                r.request_priority,
                r.request_notes,
                r.request_submitted_at
            FROM requests r
            LEFT JOIN departments d ON d.id = r.department_id
            LEFT JOIN budget_categories bc ON bc.budget_category_id = r.budget_category_id
            LEFT JOIN workflows w ON w.workflow_id = r.workflow_id
            LEFT JOIN users u ON u.user_id = r.request_submitted_by
            WHERE r.request_id = :request_id
            LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':request_id' => $requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function getAttachmentsByRequestId(int $requestId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM request_attachments WHERE request_id = :request_id ORDER BY attachment_uploaded_at ASC, attachment_id ASC');
        $stmt->execute([':request_id' => $requestId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAttachmentById(int $attachmentId, int $requestId = 0): ?array
    {
        $sql = 'SELECT * FROM request_attachments WHERE attachment_id = :attachment_id';
        $params = [':attachment_id' => $attachmentId];

        if ($requestId > 0) {
            $sql .= ' AND request_id = :request_id';
            $params[':request_id'] = $requestId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function createRequest(array $requestData, ?array $attachmentData = null)
    {
        $this->db->beginTransaction();

        try {
            $referenceNo = (string) ($requestData['request_reference_no'] ?? '');
            if ($referenceNo === '') {
                $referenceNo = $this->generateRequestReferenceNo();
            }

            $workflowId = (int) ($requestData['workflow_id'] ?? 0);
            $currentStepId = $workflowId > 0 ? $this->getFirstWorkflowStepId($workflowId) : null;

            $sql = 'INSERT INTO requests (
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
            )';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':request_reference_no' => $referenceNo,
                ':request_type' => (string) ($requestData['request_type'] ?? ''),
                ':request_title' => (string) ($requestData['request_title'] ?? ''),
                ':request_description' => $requestData['request_description'] ?? null,
                ':request_amount' => (float) ($requestData['request_amount'] ?? 0),
                ':request_currency' => (string) ($requestData['request_currency'] ?? 'INR'),
                ':department_id' => $requestData['department_id'] !== null ? (int) $requestData['department_id'] : null,
                ':request_category' => (string) ($requestData['request_category'] ?? ''),
                ':budget_category_id' => $requestData['budget_category_id'] !== null ? (int) $requestData['budget_category_id'] : null,
                ':workflow_id' => $workflowId,
                ':request_current_step_id' => $currentStepId,
                ':request_submitted_by' => (int) ($requestData['request_submitted_by'] ?? 0),
                ':request_status' => 'pending',
                ':request_priority' => (string) ($requestData['request_priority'] ?? 'medium'),
                ':request_notes' => $requestData['request_notes'] ?? null,
                ':request_submitted_at' => (string) ($requestData['request_submitted_at'] ?? date('Y-m-d H:i:s')),
            ]);

            $requestId = (int) $this->db->lastInsertId();

            if ($attachmentData !== null) {
                $this->insertAttachment($requestId, $attachmentData, (int) ($requestData['request_submitted_by'] ?? 0));
            }

            $this->db->commit();

            return $requestId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('ExpenseModel::createRequest failed: ' . $error->getMessage());

            return false;
        }
    }
}
