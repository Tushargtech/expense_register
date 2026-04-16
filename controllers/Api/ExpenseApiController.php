<?php

class ExpenseApiController extends ApiBaseController
{
    private $model;
    private $rbac;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ExpensesModel();
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
            'request_type' => strtolower(trim((string) ($source['request_type'] ?? $defaultType))),
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

        if ($size > 5 * 1024 * 1024) {
            throw new RuntimeException('Attachment size must be 5 MB or less.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($tmpPath);
        $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('Invalid attachment content type.');
        }

        $storedName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $contents = file_get_contents($tmpPath);
        if ($contents === false) {
            throw new RuntimeException('Failed to read uploaded attachment.');
        }

        return [
            'attachment_file_name' => basename($originalName),
            'attachment_stored_name' => $storedName,
            'attachment_file_path' => '',
            'attachment_file_data' => base64_encode($contents),
            'attachment_file_size' => $size,
            'attachment_mime_type' => $mimeType,
            'attachment_type' => 'other',
        ];
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
            'department_id' => (int) ($this->request->routeParam('department_id', 0) ?? 0),
            'date_from' => $this->request->queryString('date_from'),
            'date_to' => $this->request->queryString('date_to'),
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
            'limit' => max(1, min(100, (int) ($_GET['limit'] ?? 10))),
        ];

        $result = $expenseModel->getFiltered(
            $filters['search'],
            $filters['status'],
            $filters['department_id'],
            $filters['date_from'],
            $filters['date_to'],
            $filters['page'],
            $filters['limit'],
            (int) ($this->rbac()->userId() ?? 0)
        );

        $this->jsonSuccess($result['records'] ?? [], [
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
        $request = $expenseModel->getRequestDetailsById($requestId);

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
        $auth = $this->authenticatedUser();
        $rbac = $this->rbac();
        $departmentLocked = ($rbac->isManager() || $rbac->isDepartmentHead()) && (int) ($auth['department_id'] ?? 0) > 0;
        $expenseData['department_id'] = $departmentLocked ? (int) ($auth['department_id'] ?? 0) : $expenseData['department_id'];
        $expenseData['request_submitted_by'] = (int) ($auth['user_id'] ?? 0);
        $expenseData['request_notes'] = $expenseData['request_notes'] !== '' ? $expenseData['request_notes'] : null;
        $expenseData['request_description'] = $expenseData['request_description'] !== '' ? $expenseData['request_description'] : null;
        $expenseData['request_submitted_at'] = date('Y-m-d H:i:s');

        $categoryModel = new BudgetCategoryModel();
        $workflowModel = new WorkflowModel();
        $selectableCategories = $categoryModel->getSelectableCategories();
        $selectableWorkflows = $workflowModel->getSelectableWorkflows();
        $selectedCategory = null;
        $selectedWorkflow = null;

        foreach ($selectableCategories as $category) {
            if ((int) ($category['budget_category_id'] ?? 0) === $expenseData['budget_category_id']) {
                $selectedCategory = $category;
                break;
            }
        }

        foreach ($selectableWorkflows as $workflow) {
            if ((int) ($workflow['workflow_id'] ?? 0) === $expenseData['workflow_id']) {
                $selectedWorkflow = $workflow;
                break;
            }
        }

        if ($expenseData['workflow_id'] <= 0 && $expenseData['budget_category_id'] > 0) {
            $matchingWorkflows = $workflowModel->getSelectableWorkflows($expenseData['budget_category_id'], $expenseData['request_type']);
            if (!empty($matchingWorkflows)) {
                $selectedWorkflow = $matchingWorkflows[0];
                $expenseData['workflow_id'] = (int) ($selectedWorkflow['workflow_id'] ?? 0);
            }
        }

        $errors = $this->validateExpensePayload($expenseData, $selectedCategory ?? [], $selectedWorkflow ?? [], $departmentLocked);
        if (!empty($errors)) {
            $this->jsonError('Validation failed.', 422, $errors);
        }

        $expenseData['request_category'] = (string) ($selectedCategory['budget_category_name'] ?? '');
        $expenseModel = new ExpenseModel();
        $attachmentPayload = null;

        try {
            if (isset($_FILES['attachment_file']) && is_array($_FILES['attachment_file'])) {
                $file = $_FILES['attachment_file'];
                $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($errorCode !== UPLOAD_ERR_NO_FILE) {
                    if ($errorCode !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Attachment upload failed.');
                    }
                    $attachmentPayload = $this->buildAttachmentPayload($file);
                }
            }
        } catch (Throwable $error) {
            $this->jsonError($error->getMessage(), 422);
        }

        $requestId = $expenseModel->createRequest($expenseData, $attachmentPayload);
        if ($requestId === false) {
            $this->jsonError('Failed to create expense request.', 500);
        }

        RbacService::audit('request_create', ['request_id' => $requestId, 'request_type' => $expenseData['request_type']]);
        $this->jsonSuccess(['request_id' => $requestId], [], 201);
    }

    public function action(): void
    {
        $this->ensureAccess();
        $input = $this->input();
        $requestId = (int) ($input['request_id'] ?? 0);
        $action = trim((string) ($input['action'] ?? ''));
        $comment = trim((string) ($input['comment'] ?? ''));
        $reassignTo = (int) ($input['reassign_to'] ?? 0);
        $userId = (int) ($this->authenticatedUser()['user_id'] ?? 0);

        if ($requestId <= 0 || !in_array($action, ['approve', 'reject', 'reassign'], true)) {
            $this->jsonError('Invalid request or action.', 400);
        }

        $queryModel = new ExpenseQueryModel();
        $pendingAssignment = $queryModel->getPendingAssignmentForUser($requestId, $userId);
        if ($pendingAssignment === null) {
            $this->jsonError('You are not authorized to perform this action.', 403);
        }

        $request = $queryModel->getRequestById($requestId);
        if ($request === null) {
            $this->jsonError('Request not found.', 404);
        }

        $this->ensureCanAccessRequestRecord($request);

        $writeModel = new ExpenseWriteModel();
        try {
            if ($action === 'approve') {
                $writeModel->approveStep($requestId, $userId);
            } elseif ($action === 'reject') {
                if ($comment === '') {
                    $this->jsonError('A comment is required when rejecting.', 400);
                }
                $writeModel->rejectStep($requestId, $userId, $comment);
            } elseif ($action === 'reassign') {
                if ($reassignTo <= 0) {
                    $this->jsonError('Please select a user to reassign to.', 400);
                }
                $writeModel->reassignStep($requestId, $userId, $reassignTo, $comment);
            }
        } catch (Throwable $e) {
            $this->jsonError('Action failed: ' . $e->getMessage(), 500);
        }

        $this->jsonSuccess(['message' => 'Action completed successfully.']);
    }

    public function update(int $requestId): void
    {
        $this->jsonError('Expense updates are not implemented in the current UI model.', 405);
    }

    private function streamAttachment(int $attachmentId, int $requestId, bool $inline): void
    {
        $this->ensureAccess();
        if ($attachmentId <= 0 || $requestId <= 0) {
            $this->jsonError('Invalid attachment request.', 422);
        }

        $expenseModel = new ExpenseModel();
        $attachment = $expenseModel->getAttachmentById($attachmentId, $requestId);
        if ($attachment === null) {
            $this->jsonError('Attachment not found.', 404);
        }

        $request = $expenseModel->getRequestDetailsById($requestId);
        if ($request === null) {
            $this->jsonError('Expense request not found.', 404);
        }

        $this->ensureCanAccessRequestRecord($request);

        $encodedPayload = (string) ($attachment['attachment_file_data'] ?? '');
        $binaryPayload = base64_decode($encodedPayload, true);
        if ($binaryPayload === false) {
            $this->jsonError('Attachment data is corrupted.', 500);
        }

        RbacService::audit($inline ? 'attachment_view' : 'attachment_download', ['request_id' => $requestId, 'attachment_id' => $attachmentId]);
        $this->emitBinary(
            $binaryPayload,
            (string) ($attachment['attachment_file_name'] ?? 'attachment.bin'),
            (string) ($attachment['attachment_mime_type'] ?? 'application/octet-stream'),
            $inline
        );
    }

    public function downloadAttachment(): void
    {
        $this->streamAttachment((int) ($_GET['attachment_id'] ?? 0), (int) ($_GET['request_id'] ?? 0), false);
    }

    public function viewAttachment(): void
    {
        $this->streamAttachment((int) ($_GET['attachment_id'] ?? 0), (int) ($_GET['request_id'] ?? 0), true);
    }
}