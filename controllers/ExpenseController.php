<?php

class ExpenseController
{
    private function lookup(): LookupModel
    {
        return new LookupModel();
    }

    private function rbac(): RbacService
    {
        return new RbacService();
    }

    private function ensureExpenseAccess(): void
    {
        $this->ensureAuthenticated();
        if (!$this->rbac()->canAccessFinancialRequests()) {
            header('Location: ?route=forbidden&code=rbac_expense');
            exit;
        }
    }

    private function ensureCanAccessRequestRecord(array $request): void
    {
        $ownerUserId = (int) ($request['request_submitted_by'] ?? 0);
        $requestDepartmentId = (int) ($request['department_id'] ?? 0);
        if (!$this->rbac()->canAccessRequest($ownerUserId, $requestDepartmentId)) {
            flash_error('You are not authorized to access this request.');
            header('Location: ?route=expenses');
            exit;
        }
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
        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ];
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

    private function streamAttachmentFile(array $attachment, bool $inline): void
    {
        $encodedPayload = (string) ($attachment['attachment_file_data'] ?? '');
        if ($encodedPayload === '') {
            throw new RuntimeException('Attachment data not found in database.');
        }

        $binaryPayload = base64_decode($encodedPayload, true);
        if ($binaryPayload === false) {
            throw new RuntimeException('Attachment data is corrupted.');
        }

        $fileName = (string) ($attachment['attachment_file_name'] ?? 'attachment.bin');
        $mimeType = (string) ($attachment['attachment_mime_type'] ?? 'application/octet-stream');
        $disposition = $inline ? 'inline' : 'attachment';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) strlen($binaryPayload));
        header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($fileName) . '"');
        header('X-Content-Type-Options: nosniff');
        echo $binaryPayload;
        exit;
    }

    private function ensureAuthenticated(): void
    {
        if (empty($_SESSION['auth']['is_logged_in'])) {
            flash_error('Please login to continue.');
            header('Location: ?route=dashboard');
            exit;
        }
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

    private function isValidExpensePayload(array $expenseData, array $selectedCategory, array $selectedWorkflow, bool $departmentLocked): bool
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

        if (!in_array($expenseData['request_type'], $allowedTypes, true)) {
            return false;
        }

        if ($expenseData['request_title'] === '' || $expenseData['request_amount'] === '') {
            return false;
        }

        if (!is_numeric($expenseData['request_amount']) || (float) $expenseData['request_amount'] <= 0) {
            return false;
        }

        if (!in_array($expenseData['request_currency'], $allowedCurrencies, true)) {
            return false;
        }

        if ($expenseData['department_id'] <= 0) {
            return false;
        }

        if ($expenseData['budget_category_id'] <= 0 || empty($selectedCategory)) {
            return false;
        }

        if ($expenseData['workflow_id'] <= 0 || empty($selectedWorkflow)) {
            return false;
        }

        if (!in_array($expenseData['request_priority'], $allowedPriorities, true)) {
            return false;
        }

        if ((int) ($selectedCategory['budget_category_is_active'] ?? 0) !== 1) {
            return false;
        }

        if ((int) ($selectedWorkflow['workflow_is_active'] ?? 0) !== 1) {
            return false;
        }

        $categoryType = strtolower(trim((string) ($selectedCategory['budget_category_type'] ?? '')));
        $workflowType = strtolower(trim((string) ($selectedWorkflow['workflow_type'] ?? '')));
        $workflowBudgetCategoryId = (int) ($selectedWorkflow['budget_category_id'] ?? 0);
        $selectedCategoryId = (int) ($selectedCategory['budget_category_id'] ?? 0);

        return $categoryType === $expenseData['request_type']
            && $workflowType === $expenseData['request_type']
            && $workflowBudgetCategoryId > 0
            && $workflowBudgetCategoryId === $selectedCategoryId
            && $workflowBudgetCategoryId === $expenseData['budget_category_id'];
    }

    public function create()
    {
        $this->ensureExpenseAccess();

        $departmentModel = new DepartmentModel();
        $categoryModel = new BudgetCategoryModel();
        $workflowModel = new WorkflowModel();
        $lookupModel = $this->lookup();

        $departments = $departmentModel->getAllDepartments();
        $categories = $categoryModel->getSelectableCategories();
        $workflows = $workflowModel->getSelectableWorkflows();
        $requestTypeOptions = $lookupModel->getRequestTypes();
        $requestCurrencyOptions = $lookupModel->getRequestCurrencies();
        $requestPriorityOptions = $lookupModel->getRequestPriorities();

        $defaultRequestType = strtolower((string) ($requestTypeOptions[0] ?? ''));
        $defaultCurrency = strtoupper((string) ($requestCurrencyOptions[0] ?? ''));
        $defaultPriority = strtolower((string) ($requestPriorityOptions[0] ?? ''));

        $auth = $_SESSION['auth'] ?? [];
        $userRole = strtolower(trim((string) ($auth['role'] ?? '')));
        $departmentLocked = in_array($userRole, ['manager', 'dept_head'], true) && (int) ($auth['department_id'] ?? 0) > 0;
        $selectedDepartmentId = $departmentLocked ? (int) ($auth['department_id'] ?? 0) : 0;

        $pageTitle = 'Create Expense Request - Expense Register';
        $pageStyles = ['assets/css/app.css'];
        $envConfig = $GLOBALS['envConfig'] ?? [];
        $userName = (string) ($_SESSION['auth']['name'] ?? 'User');
        $activeMenu = 'expense-list';
        $isReadOnly = false;
        $formTitle = 'Create Expense Request';
        $formAction = '?route=expenses/create';
        $submitLabel = 'Submit Request';
        $expense = [
            'request_type' => $defaultRequestType,
            'request_title' => '',
            'request_description' => '',
            'request_amount' => '',
            'request_currency' => $defaultCurrency,
            'department_id' => $selectedDepartmentId,
            'budget_category_id' => 0,
            'request_priority' => $defaultPriority,
            'request_notes' => '',
        ];

        require ROOT_PATH . '/views/templates/app_layout.php';
        renderAppLayoutStart([
            'pageTitle' => $pageTitle,
            'pageStyles' => $pageStyles,
            'activeMenu' => $activeMenu,
        ]);
        require ROOT_PATH . '/views/ExpenseManagement/expense_creation.php';
        renderAppLayoutEnd();
    }

    public function review()
    {
        $this->ensureExpenseAccess();

        $requestId = (int) ($_GET['id'] ?? 0);
        if ($requestId <= 0) {
            flash_error('Invalid expense request.');
            header('Location: ?route=expenses');
            exit;
        }

        $expenseModel = new ExpenseModel();
        $request = $expenseModel->getRequestDetailsById($requestId);
        if ($request === null) {
            flash_error('Expense request not found.');
            header('Location: ?route=expenses');
            exit;
        }
        $this->ensureCanAccessRequestRecord($request);

        $attachments = $expenseModel->getAttachmentsByRequestId($requestId);
        $departments = [];
        if (!empty($request['department_id'])) {
            $departmentModel = new DepartmentModel();
            $departments = $departmentModel->getAllDepartments();
        }

        $requestAttachments = [];
        foreach ($attachments as $attachment) {
            $isAvailable = trim((string) ($attachment['attachment_file_data'] ?? '')) !== '';
            $requestAttachments[] = [
                'attachment_id' => (int) ($attachment['attachment_id'] ?? 0),
                'attachment_file_name' => (string) ($attachment['attachment_file_name'] ?? ''),
                'attachment_stored_name' => (string) ($attachment['attachment_stored_name'] ?? ''),
                'attachment_file_size' => (int) ($attachment['attachment_file_size'] ?? 0),
                'attachment_mime_type' => (string) ($attachment['attachment_mime_type'] ?? ''),
                'attachment_type' => (string) ($attachment['attachment_type'] ?? 'other'),
                'is_available' => $isAvailable,
            ];
        }

        $pageTitle = 'Review Expense Request - Expense Register';
        $pageStyles = ['assets/css/app.css'];
        $envConfig = $GLOBALS['envConfig'] ?? [];
        $userName = (string) ($_SESSION['auth']['name'] ?? 'User');
        $activeMenu = 'expense-list';
        $lookupModel = $this->lookup();
        $requestTypeOptions = $lookupModel->getRequestTypes();
        $requestCurrencyOptions = $lookupModel->getRequestCurrencies();
        $requestPriorityOptions = $lookupModel->getRequestPriorities();
        $isReadOnly = true;
        $formTitle = 'Review Expense Request';
        $formAction = '?route=expenses/create';
        $submitLabel = 'Submit Request';
        $expense = $request;

        require ROOT_PATH . '/views/templates/app_layout.php';
        renderAppLayoutStart([
            'pageTitle' => $pageTitle,
            'pageStyles' => $pageStyles,
            'activeMenu' => $activeMenu,
        ]);
        require ROOT_PATH . '/views/ExpenseManagement/expense_creation.php';
        renderAppLayoutEnd();
    }

    public function downloadAttachment()
    {
        $this->ensureExpenseAccess();

        $attachmentId = (int) ($_GET['attachment_id'] ?? 0);
        $requestId = (int) ($_GET['request_id'] ?? 0);

        if ($attachmentId <= 0 || $requestId <= 0) {
            flash_error('Invalid attachment request.');
            header('Location: ?route=expenses');
            exit;
        }

        $expenseModel = new ExpenseModel();
        $attachment = $expenseModel->getAttachmentById($attachmentId, $requestId);

        if ($attachment === null) {
            flash_error('Attachment not found.');
            header('Location: ?route=expenses/review&id=' . $requestId);
            exit;
        }

        $request = $expenseModel->getRequestDetailsById($requestId);
        if ($request === null) {
            flash_error('Expense request not found.');
            header('Location: ?route=expenses');
            exit;
        }
        $this->ensureCanAccessRequestRecord($request);

        try {
            RbacService::audit('attachment_download', ['request_id' => $requestId, 'attachment_id' => $attachmentId]);
            $this->streamAttachmentFile($attachment, false);
        } catch (Throwable $error) {
            flash_error($error->getMessage());
            header('Location: ?route=expenses/review&id=' . $requestId);
            exit;
        }
    }

    public function viewAttachment()
    {
        $this->ensureExpenseAccess();

        $attachmentId = (int) ($_GET['attachment_id'] ?? 0);
        $requestId = (int) ($_GET['request_id'] ?? 0);

        if ($attachmentId <= 0 || $requestId <= 0) {
            flash_error('Invalid attachment request.');
            header('Location: ?route=expenses');
            exit;
        }

        $expenseModel = new ExpenseModel();
        $attachment = $expenseModel->getAttachmentById($attachmentId, $requestId);

        if ($attachment === null) {
            flash_error('Attachment not found.');
            header('Location: ?route=expenses/review&id=' . $requestId);
            exit;
        }

        $request = $expenseModel->getRequestDetailsById($requestId);
        if ($request === null) {
            flash_error('Expense request not found.');
            header('Location: ?route=expenses');
            exit;
        }
        $this->ensureCanAccessRequestRecord($request);

        try {
            RbacService::audit('attachment_view', ['request_id' => $requestId, 'attachment_id' => $attachmentId]);
            $this->streamAttachmentFile($attachment, true);
        } catch (Throwable $error) {
            flash_error($error->getMessage());
            header('Location: ?route=expenses/review&id=' . $requestId);
            exit;
        }
    }

    public function store()
    {
        $this->ensureExpenseAccess();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ?route=expenses/create');
            exit;
        }

        $expenseData = $this->normalizeExpensePayload($_POST);
        $auth = $_SESSION['auth'] ?? [];
        $userRole = strtolower(trim((string) ($auth['role'] ?? '')));
        $departmentLocked = in_array($userRole, ['manager', 'dept_head'], true) && (int) ($auth['department_id'] ?? 0) > 0;
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

        if (!$this->isValidExpensePayload($expenseData, $selectedCategory ?? [], $selectedWorkflow ?? [], $departmentLocked)) {
            flash_error('Please complete all required fields and choose a matching category/workflow.');
            header('Location: ?route=expenses/create');
            exit;
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
            flash_error($error->getMessage());
            header('Location: ?route=expenses/create');
            exit;
        }

        $requestId = $expenseModel->createRequest($expenseData, $attachmentPayload);

        if ($requestId !== false) {
            RbacService::audit('request_create', ['request_id' => $requestId, 'request_type' => $expenseData['request_type']]);
            flash_success('Expense request created successfully.');
            header('Location: ?route=expenses');
        } else {
            flash_error('Failed to create expense request.');
            header('Location: ?route=expenses/create');
        }

        exit;
    }

    public function list()
    {
        $this->ensureExpenseAccess();

        $expenseModel = new ExpenseModel();
        $rbac = $this->rbac();
        $currentUserId = $rbac->userId();

        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'department_id' => 0,
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
            'limit' => 10,
        ];

        $departmentModel = new DepartmentModel();
        $departments = $departmentModel->getAllDepartments();

        $result = $expenseModel->getFiltered(
            $filters['search'],
            $filters['status'],
            $filters['department_id'],
            $filters['date_from'],
            $filters['date_to'],
            $filters['page'],
            $filters['limit'],
            $currentUserId
        );

        $requests = $result['records'] ?? [];
        $totalRecords = $result['total'] ?? 0;
        $currentPage = $filters['page'];
        $perPage = $filters['limit'];
        $totalPages = ceil($totalRecords / $perPage) ?: 1;

        unset($filters['page'], $filters['limit']);

        $pageTitle = 'Expense Requests - Expense Register';
        $pageStyles = ['assets/css/app.css'];
        $envConfig = $GLOBALS['envConfig'] ?? [];
        $userName = (string) ($_SESSION['auth']['name'] ?? 'User');
        $activeMenu = 'expense-list';
        $expenseScopeRole = $rbac->role();
        $canReviewExpenseRequests = $rbac->canReviewExpenseRequests();
        $canFilterByDepartment = false;

        require ROOT_PATH . '/views/templates/app_layout.php';
        renderAppLayoutStart([
            'pageTitle' => $pageTitle,
            'pageStyles' => $pageStyles,
            'activeMenu' => $activeMenu,
        ]);
        require ROOT_PATH . '/views/ExpenseManagement/expense_list.php';
        renderAppLayoutEnd();
    }
}
