<?php

class ExpenseApiController extends ApiBaseController
{
    private $model;
    private $rbac;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ExpenseModel();
        $this->rbac = new RbacService();
    }


    private function lookup(): LookupModel
    {
        return new LookupModel();
    }

    private function ensureAccess(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canAccessFinancialRequests(), 'Forbidden');
    }

    private function normalizeRequestTypeValue(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'expense' => 'expense',
            'purchase' => 'purchase',
            default => $normalized,
        };
    }
    private function normalizeExpensePayload(array $source): array
    {
        $lookup = $this->lookup();
        $requestTypeOptions = $lookup->getRequestTypes();
        $currencyOptions = $lookup->getRequestCurrencies();
        $priorityOptions = $lookup->getRequestPriorities();

        $defaultType = strtolower((string) ($requestTypeOptions[0] ?? ''));
        $defaultCurrency = strtoupper((string) ($currencyOptions[0] ?? ''));
        $defaultPriority = strtolower((string) ($priorityOptions[0] ?? ''));

        return [
            'request_type' => $this->normalizeRequestTypeValue((string) ($source['request_type'] ?? $defaultType)),
            'request_title' => trim((string) ($source['request_title'] ?? '')),
            'request_description' => trim((string) ($source['request_description'] ?? '')),
            'request_amount' => trim((string) ($source['request_amount'] ?? '')),
            'request_currency' => strtoupper(trim((string) ($source['request_currency'] ?? $defaultCurrency))),
            'department_id' => (int) ($source['department_id'] ?? 0),
            'budget_category_id' => (int) ($source['budget_category_id'] ?? 0),
            'workflow_id' => (int) ($source['workflow_id'] ?? 0),
            'request_priority' => strtolower(trim((string) ($source['request_priority'] ?? $defaultPriority))),
            'request_notes' => trim((string) ($source['request_notes'] ?? '')),
        ];
    }

    private function generateRequestReferenceNo(): string
    {
        try {
            $randomPart = strtoupper(bin2hex(random_bytes(3)));
        } catch (Throwable $error) {
            $randomPart = strtoupper(substr(sha1(uniqid((string) mt_rand(), true)), 0, 6));
        }

        return 'EXP-' . date('Ymd') . '-' . $randomPart;
    }

    private function resolveStepAssigneeIds(array $step, int $departmentId, int $requesterId, WorkflowModel $workflowModel): array
    {
        $explicitUserId = (int) ($step['step_approver_user_id'] ?? 0);
        if ($explicitUserId > 0) {
            return [$explicitUserId];
        }

        $approverRole = strtolower(trim((string) ($step['step_approver_role'] ?? '')));
        $approverType = strtolower(trim((string) ($step['step_approver_type'] ?? '')));
        if ($approverType === 'manager') {
            $managerAssigneeId = $this->model->findManagerAssigneeForDepartment($requesterId, $departmentId);
            return $managerAssigneeId !== null && $managerAssigneeId > 0 ? [$managerAssigneeId] : [];
        }

        if ($approverType === 'department_head') {
            $departmentHeadId = $this->model->findDepartmentHeadUserId($departmentId);
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

    private function buildAttachmentPayload(array $file): array
    {
        $originalName = (string) ($file['name'] ?? '');
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($originalName === '' || $tmpPath === '' || $size <= 0) {
            throw new RuntimeException('Invalid uploaded file.');
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Invalid attachment type. Allowed: PDF, JPG, JPEG, PNG.');
        }

        if ($size > $this->fileUploadMaxSizeBytes()) {
            throw new RuntimeException('Attachment size must be ' . $this->fileUploadMaxSizeMb() . ' MB or less.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($tmpPath);
        $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('Invalid attachment content type.');
        }

        $storedName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $relativeFolder = $this->resolveAttachmentFolder('other');
        $this->ensureAttachmentFolderExists($relativeFolder);
        $relativePath = $relativeFolder . '/' . $storedName;
        $absolutePath = ROOT_PATH . '/' . ltrim($relativePath, '/');

        // Verify folder exists and is writable
        $folderPath = dirname($absolutePath);
        if (!is_dir($folderPath)) {
            throw new RuntimeException('Upload folder does not exist: ' . $folderPath);
        }

        if (!is_writable($folderPath)) {
            throw new RuntimeException('Upload folder is not writable: ' . $folderPath);
        }

        if (!file_exists($tmpPath)) {
            throw new RuntimeException('Temporary file does not exist.');
        }

        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            throw new RuntimeException('Failed to store uploaded attachment file. Check folder permissions.');
        }

        return [
            'attachment_file_name' => basename($originalName),
            'attachment_stored_name' => $storedName,
            'attachment_file_path' => $relativePath,
            'attachment_file_size' => $size,
            'attachment_mime_type' => $mimeType,
            'attachment_type' => 'other',
        ];
    }

    private function resolveAttachmentFolder(string $attachmentType): string
    {
        return match ($attachmentType) {
            'invoice' => 'uploads/invoices',
            'receipt' => 'uploads/receipts',
            default => 'uploads/others',
        };
    }

    private function ensureAttachmentFolderExists(string $relativeFolder): void
    {
        $absoluteFolder = ROOT_PATH . '/' . ltrim($relativeFolder, '/');
        if (!is_dir($absoluteFolder) && !mkdir($absoluteFolder, 0775, true) && !is_dir($absoluteFolder)) {
            throw new RuntimeException('Failed to create attachment upload directory.');
        }
    }

    private function fileUploadMaxSizeMb(): int
    {
        return max(1, (int) (($GLOBALS['envConfig']['app']['file_upload_max_size_mb'] ?? FILE_UPLOAD_MAX_SIZE_MB)));
    }

    private function fileUploadMaxSizeBytes(): int
    {
        return $this->fileUploadMaxSizeMb() * 1024 * 1024;
    }

    private function normalizeUploadedFiles(string $fieldName): array
    {
        if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
            return [];
        }

        $raw = $_FILES[$fieldName];
        if (!isset($raw['name'])) {
            return [];
        }

        if (!is_array($raw['name'])) {
            return [$raw];
        }

        $normalized = [];
        $count = count($raw['name']);
        for ($index = 0; $index < $count; $index++) {
            $normalized[] = [
                'name' => (string) ($raw['name'][$index] ?? ''),
                'type' => (string) ($raw['type'][$index] ?? ''),
                'tmp_name' => (string) ($raw['tmp_name'][$index] ?? ''),
                'size' => (int) ($raw['size'][$index] ?? 0),
                'error' => (int) ($raw['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            ];
        }

        return $normalized;
    }

    private function ensureCanAccessRequestRecord(array $request): void
    {
        $ownerUserId = (int) ($request['request_submitted_by'] ?? 0);
        $requestDepartmentId = (int) ($request['department_id'] ?? 0);
        if (!$this->rbac()->canAccessRequest($ownerUserId, $requestDepartmentId)) {
            $this->jsonError('You are not authorized to access this request.', 403);
        }
    }

    public function handle(): void
    {
        $method = $this->method();
        $id = $this->idFromQuery();

        if ($method === 'GET' && $id > 0) {
            $this->show($id);
            return;
        }

        if ($method === 'GET') {
            $this->index();
            return;
        }

        if ($method === 'POST' && $id <= 0) {
            $this->store();
            return;
        }

        if (in_array($method, ['PUT', 'PATCH', 'POST'], true) && $id > 0) {
            $this->update($id);
            return;
        }

        $this->jsonError('Method not allowed', 405);
    }

    private function validateExpensePayload(array $expenseData, array $selectedCategory, array $selectedWorkflow, bool $departmentLocked): array
    {
        $lookup = $this->lookup();
        $allowedTypes = $lookup->getRequestTypes();
        $allowedCurrencies = $lookup->getRequestCurrencies();
        $allowedPriorities = $lookup->getRequestPriorities();
        if ($allowedTypes === [] && $expenseData['request_type'] !== '') {
            $allowedTypes = [$expenseData['request_type']];
        }
        if ($allowedCurrencies === [] && $expenseData['request_currency'] !== '') {
            $allowedCurrencies = [$expenseData['request_currency']];
        }
        if ($allowedPriorities === [] && $expenseData['request_priority'] !== '') {
            $allowedPriorities = [$expenseData['request_priority']];
        }
        $errors = [];

        if (!in_array($expenseData['request_type'], $allowedTypes, true)) {
            $errors['request_type'] = 'Invalid request type.';
        }
        if ($expenseData['request_title'] === '') {
            $errors['request_title'] = 'Title is required.';
        }
        if ($expenseData['request_amount'] === '' || !is_numeric($expenseData['request_amount']) || (float) $expenseData['request_amount'] <= 0) {
            $errors['request_amount'] = 'Amount must be greater than zero.';
        }
        if (!in_array($expenseData['request_currency'], $allowedCurrencies, true)) {
            $errors['request_currency'] = 'Invalid currency.';
        }
        if ($expenseData['department_id'] <= 0) {
            $errors['department_id'] = 'Department is required.';
        }
        if ($expenseData['budget_category_id'] <= 0 || empty($selectedCategory)) {
            $errors['budget_category_id'] = 'Budget category is required.';
        }
        if ($expenseData['workflow_id'] <= 0 || empty($selectedWorkflow)) {
            $errors['workflow_id'] = 'Workflow is required.';
        }
        if (!in_array($expenseData['request_priority'], $allowedPriorities, true)) {
            $errors['request_priority'] = 'Invalid priority.';
        }
        if ((int) ($selectedCategory['budget_category_is_active'] ?? 0) !== 1) {
            $errors['budget_category_id'] = 'Selected category is inactive.';
        }
        if ((int) ($selectedWorkflow['workflow_is_active'] ?? 0) !== 1) {
            $errors['workflow_id'] = 'Selected workflow is inactive.';
        }

        $categoryType = strtolower(trim((string) ($selectedCategory['budget_category_type'] ?? '')));
        $workflowType = strtolower(trim((string) ($selectedWorkflow['workflow_type'] ?? '')));
        $workflowBudgetCategoryId = (int) ($selectedWorkflow['budget_category_id'] ?? 0);
        $selectedCategoryId = (int) ($selectedCategory['budget_category_id'] ?? 0);
        if (
            $categoryType !== $expenseData['request_type'] ||
            $workflowType !== $expenseData['request_type'] ||
            $workflowBudgetCategoryId <= 0 ||
            $workflowBudgetCategoryId !== $selectedCategoryId ||
            $workflowBudgetCategoryId !== (int) $expenseData['budget_category_id']
        ) {
            $errors['request_type'] = 'Category and workflow must match request type.';
        }

        if ($departmentLocked && (int) ($this->authenticatedUser()['department_id'] ?? 0) <= 0) {
            $errors['department_id'] = 'Department scope is missing.';
        }

        return $errors;
    }

    public function index(): void
    {
        $this->ensureAccess();
        $expenseModel = new ExpenseModel();
        $filters = [
            'search' => $this->request->queryString('search'),
            'status' => $this->request->queryString('status'),
            'department' => (string) ((int) ($this->request->routeParam('department_id', 0) ?? 0)),
            'date_from' => $this->request->queryString('date_from'),
            'date_to' => $this->request->queryString('date_to'),
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
            'limit' => max(1, min(100, (int) ($_GET['limit'] ?? 10))),
        ];

        if ($filters['department'] === '0') {
            $filters['department'] = '';
        }

        $result = $expenseModel->getExpenses($filters, $filters['page'], $filters['limit']);

        $this->jsonSuccess($result['expenses'] ?? [], [
            'pagination' => [
                'page' => $filters['page'],
                'limit' => $filters['limit'],
                'total' => (int) ($result['total'] ?? 0),
                'pages' => max(1, (int) ceil(((int) ($result['total'] ?? 0)) / $filters['limit'])),
            ],
        ]);
    }

    public function show(int $requestId): void
    {
        $this->ensureAccess();
        $expenseModel = new ExpenseModel();
        $request = $expenseModel->getRequestReviewDetails($requestId);

        if ($request === null) {
            $this->jsonError('Expense request not found.', 404);
        }

        $this->ensureCanAccessRequestRecord($request);
        $attachments = $expenseModel->getAttachmentsByRequestId($requestId);

        $this->jsonSuccess([
            'request' => $request,
            'attachments' => $attachments,
        ]);
    }

    public function review(): void
    {
        $requestId = $this->idFromQuery();
        $this->show($requestId);
    }

    public function store(): void
    {
        $this->ensureAccess();
        $input = $this->input();
        $expenseData = $this->normalizeExpensePayload($input);
        $selectedCategory = [];
        $selectedWorkflow = [];
        $departmentLocked = false;
        $budget = null;

        $departmentLocked = $this->departmentLocked();
        if ($expenseData['department_id'] <= 0) {
            $this->jsonError('Department is required.');
            return;
        }

        $expenseModel = new ExpenseModel();
        $categoryData = $expenseModel->getBudgetCategoryById((int) $expenseData['budget_category_id']);
        if ($categoryData === null || empty($categoryData)) {
            $this->jsonError('Budget category not found.');
            return;
        }

        $selectedCategory = $categoryData;
        $workflowData = $expenseModel->getWorkflowById((int) $expenseData['workflow_id']);
        if ($workflowData === null || empty($workflowData)) {
            $this->jsonError('Workflow not found.');
            return;
        }

        $selectedWorkflow = $workflowData;
        $payloadErrors = $this->validateExpensePayload($expenseData, $selectedCategory, $selectedWorkflow, $departmentLocked);
        if (!empty($payloadErrors)) {
            $this->jsonError('Validation failed', 400, ['errors' => $payloadErrors]);
            return;
        }

        try {
            $attachmentFiles = $this->normalizeUploadedFiles('attachment_file');
            $attachmentTypes = [];
            if (isset($_POST['attachment_type'])) {
                $types = $_POST['attachment_type'];
                if (is_array($types)) {
                    $attachmentTypes = array_map('strval', $types);
                } elseif (is_string($types)) {
                    $attachmentTypes = [$types];
                }
            }

            $requestPayload = [
                'request_type' => $expenseData['request_type'],
                'request_title' => $expenseData['request_title'],
                'request_description' => $expenseData['request_description'] !== '' ? $expenseData['request_description'] : null,
                'request_amount' => (float) $expenseData['request_amount'],
                'request_currency' => $expenseData['request_currency'],
                'department_id' => $expenseData['department_id'],
                'budget_category_id' => $expenseData['budget_category_id'],
                'workflow_id' => $expenseData['workflow_id'],
                'request_priority' => $expenseData['request_priority'],
                'request_notes' => $expenseData['request_notes'] !== '' ? $expenseData['request_notes'] : null,
            ];

            $requestId = $expenseModel->createRequest($requestPayload, $attachmentFiles, $attachmentTypes);
            if ($requestId <= 0) {
                $this->jsonError('Failed to create request.');
                return;
            }

            $this->jsonSuccess([
                'request_id' => $requestId,
            ], ['message' => 'Request created successfully.']);
        } catch (Throwable $error) {
            error_log('ExpenseApiController::store failed: ' . $error->getMessage());
            $this->jsonError($error->getMessage());
        }
    }

    public function update(int $requestId): void
    {
        $this->ensureAccess();
        $input = $this->input();
        $expenseModel = new ExpenseModel();
        $request = $expenseModel->getRequestReviewDetails($requestId);

        if ($request === null || empty($request)) {
            $this->jsonError('Request not found.');
            return;
        }

        $this->ensureCanAccessRequestRecord($request);
        $expenseData = $this->normalizeExpensePayload($input);
        $categoryData = $expenseModel->getBudgetCategoryById((int) $expenseData['budget_category_id']);
        if ($categoryData === null || empty($categoryData)) {
            $this->jsonError('Budget category not found.');
            return;
        }

        $workflowData = $expenseModel->getWorkflowById((int) $expenseData['workflow_id']);
        if ($workflowData === null || empty($workflowData)) {
            $this->jsonError('Workflow not found.');
            return;
        }

        $payloadErrors = $this->validateExpensePayload($expenseData, $categoryData, $workflowData, false);
        if (!empty($payloadErrors)) {
            $this->jsonError('Validation failed', 400, ['errors' => $payloadErrors]);
            return;
        }

        try {
            $attachmentFiles = $this->normalizeUploadedFiles('attachment_file');
            $attachmentTypes = [];
            if (isset($_POST['attachment_type'])) {
                $types = $_POST['attachment_type'];
                if (is_array($types)) {
                    $attachmentTypes = array_map('strval', $types);
                } elseif (is_string($types)) {
                    $attachmentTypes = [$types];
                }
            }

            $updatePayload = [
                'request_type' => $expenseData['request_type'],
                'request_title' => $expenseData['request_title'],
                'request_description' => $expenseData['request_description'] !== '' ? $expenseData['request_description'] : null,
                'request_amount' => (float) $expenseData['request_amount'],
                'request_currency' => $expenseData['request_currency'],
                'request_priority' => $expenseData['request_priority'],
                'request_notes' => $expenseData['request_notes'] !== '' ? $expenseData['request_notes'] : null,
            ];

            $success = $expenseModel->updateRequestBeforeFirstApproval($requestId, $updatePayload, $attachmentFiles, $attachmentTypes);
            if (!$success) {
                $this->jsonError('Failed to update request.');
                return;
            }

            $this->jsonSuccess([
                'request_id' => $requestId,
            ], ['message' => 'Request updated successfully.']);
        } catch (Throwable $error) {
            error_log('ExpenseApiController::update failed: ' . $error->getMessage());
            $this->jsonError($error->getMessage());
        }
    }
}
