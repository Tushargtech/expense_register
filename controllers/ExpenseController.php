<?php

class ExpenseController
{
    private ExpenseModel $model;
    private RbacService $rbac;

    public function __construct()
    {
        $this->model = new ExpenseModel();
        $this->rbac = new RbacService();
    }

    private function ensureAuthenticated(): void
    {
        if (empty($_SESSION['auth']['is_logged_in'])) {
            flash_error('Please login to continue.');
            header('Location: ' . buildCleanRouteUrl('login'));
            exit;
        }
    }

    private function ensureExpenseAccess(): void
    {
        $this->ensureAuthenticated();

        if (!$this->rbac->canAccessFinancialRequests()) {
            header('Location: ' . buildCleanRouteUrl('forbidden'));
            exit;
        }
    }

    private function lookup(): LookupModel
    {
        return new LookupModel();
    }

    private function departmentModel(): DepartmentModel
    {
        return new DepartmentModel();
    }

    private function budgetCategoryModel(): BudgetCategoryModel
    {
        return new BudgetCategoryModel();
    }
    private function resolveAuthenticatedDepartmentId(): int
    {
        $userId = (int) ($_SESSION['auth']['user_id'] ?? 0);
        if ($userId <= 0) {
            return 0;
        }

        $userModel = new UserModel();
        $user = $userModel->getUserById($userId);
        $departmentId = (int) ($user['department_id'] ?? 0);

        if ($departmentId > 0) {
            $_SESSION['auth']['department_id'] = $departmentId;
        }

        return $departmentId;
    }

    private function workflowModel(): WorkflowModel
    {
        return new WorkflowModel();
    }

    private function requestTypeOptions(): array
    {
        return [
            'expense' => 'Expense',
            'purchase' => 'Purchase',
        ];
    }

    private function priorityOptions(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
        ];
    }

    private function normalizeRequestTypeValue(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace('_', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return match ($normalized) {
            'expense' => 'expense',

            'reimbursable' => 'expense',
            'purchase' => 'purchase',
            'company paid' => 'purchase',
            default => $normalized,
        };
    }

    private function normalizeCreatePayload(array $source): array
    {
        $attachmentTypes = [];
        if (isset($source['attachment_type']) && is_array($source['attachment_type'])) {
            foreach ($source['attachment_type'] as $type) {
                $normalized = strtolower(trim((string) $type));
                if ($normalized !== '') {
                    $attachmentTypes[] = $normalized;
                }
            }
        }

        return [
            'request_reference_no' => trim((string) ($source['request_reference_no'] ?? '')),
            'request_title' => trim((string) ($source['request_title'] ?? '')),
            'request_type' => $this->normalizeRequestTypeValue((string) ($source['request_type'] ?? 'expense')),
            'request_amount' => trim((string) ($source['request_amount'] ?? '')),
            'department_id' => (int) ($source['department_id'] ?? 0),
            'budget_category_id' => (int) ($source['budget_category_id'] ?? 0),
            'request_priority' => strtolower(trim((string) ($source['request_priority'] ?? 'low'))),
            'request_description' => trim((string) ($source['request_description'] ?? '')),
            'request_notes' => trim((string) ($source['request_notes'] ?? '')),
            'attachment_types' => $attachmentTypes,
        ];
    }


    private function resolveWorkflowSelection(string $requestType, string $requestAmount, int $departmentId, int $requesterId): array

    {
        $workflowModel = $this->workflowModel();
        $requestType = strtolower(trim($requestType));
        $amount = is_numeric($requestAmount) ? (float) $requestAmount : 0.0;

        if ($requestType === '' || $amount <= 0) {
            return [null, null, null];
        }

        $workflow = $workflowModel->getWorkflowForRequestCriteria($requestType, $amount);
        if (!is_array($workflow)) {
            return [null, null, null];
        }

        $workflowId = (int) ($workflow['workflow_id'] ?? 0);
        if ($workflowId <= 0) {
            return [null, null, null];
        }

        $steps = $workflowModel->getWorkflowStepsByWorkflowId($workflowId);
        $firstStep = is_array($steps[0] ?? null) ? $steps[0] : [];
        $firstStepId = (int) ($firstStep['step_id'] ?? 0);
        $firstStepAssigneeIds = $this->resolveStepAssigneeIds($firstStep, $departmentId, $requesterId);

        return [$workflow, $firstStepId > 0 ? $firstStepId : null, $firstStepAssigneeIds];
    }

    private function resolveStepAssigneeIds(array $step, int $departmentId, int $requesterId): array
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
        foreach ($this->workflowModel()->getActiveUsers() as $user) {
            if (strtolower(trim((string) ($user['approver_role'] ?? ''))) === $approverRole) {
                $userId = (int) ($user['user_id'] ?? 0);
                if ($userId > 0) {
                    $assigneeIds[] = $userId;
                }
            }
        }

        return array_values(array_unique($assigneeIds));
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

        @chmod($absoluteFolder, 0775);

        if (!is_writable($absoluteFolder)) {
            throw new RuntimeException('Upload folder is not writable: ' . $absoluteFolder);
        }
    }

    private function buildAttachmentPayload(array $file, string $attachmentType): array
    {
        $originalName = trim((string) ($file['name'] ?? ''));
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($originalName === '' || $tmpPath === '' || $size <= 0) {
            throw new RuntimeException('Invalid attachment upload.');
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc'];
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('Invalid attachment type. Allowed: PDF, JPG, JPEG, PNG, DOC.');
        }

        if ($size > $this->fileUploadMaxSizeBytes()) {
            throw new RuntimeException('Attachment size must be ' . $this->fileUploadMaxSizeMb() . ' MB or less.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($tmpPath);
        $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('Invalid attachment content type.');
        }

        $storedName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $relativeFolder = $this->resolveAttachmentFolder($attachmentType);
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
            'attachment_type' => $attachmentType,
            'attachment_uploaded_by' => (int) ($_SESSION['auth']['user_id'] ?? 0),
        ];
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
                'error' => (int) ($raw['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($raw['size'][$index] ?? 0),
            ];
        }

        return $normalized;
    }


    private function validateCreatePayload(array $payload, array $category, array $workflow, ?int $firstStepId, array $firstStepAssigneeIds): array

    {
        $errors = [];
        $allowedTypes = array_keys($this->requestTypeOptions());
        $allowedPriorities = array_keys($this->priorityOptions());
        $allowedAttachmentTypes = ['invoice', 'receipt', 'other'];

        if (!in_array($payload['request_type'], $allowedTypes, true)) {
            $errors[] = 'Please select a valid request type.';
        }

        if ($payload['request_reference_no'] === '') {
            $errors[] = 'Reference number is required.';
        } elseif (strlen($payload['request_reference_no']) > 30) {
            $errors[] = 'Reference number must be 30 characters or less.';
        }

        if ($payload['request_title'] === '') {
            $errors[] = 'Title is required.';
        }

        if ($payload['request_amount'] === '' || !is_numeric($payload['request_amount']) || (float) $payload['request_amount'] <= 0) {
            $errors[] = 'Amount must be greater than zero.';
        }

        if ($payload['request_description'] === '') {
            $errors[] = 'Description is required.';
        }

        if ($payload['department_id'] <= 0) {
            $errors[] = 'Your user account is not assigned to a department. Please contact HR/Admin.';
        }

        if ($payload['budget_category_id'] <= 0) {
            $errors[] = 'Budget category is required.';
        }

        if (!in_array($payload['request_priority'], $allowedPriorities, true)) {
            $errors[] = 'Please select a valid priority.';
        }

        if (isset($payload['attachment_types']) && is_array($payload['attachment_types'])) {
            foreach ($payload['attachment_types'] as $attachmentType) {
                if ($attachmentType !== '' && !in_array($attachmentType, $allowedAttachmentTypes, true)) {
                    $errors[] = 'Please select a valid attachment type.';
                    break;
                }
            }
        }

        if ($category === [] || (int) ($category['budget_category_id'] ?? 0) <= 0) {
            $errors[] = 'Selected budget category was not found.';
        } elseif ($this->normalizeRequestTypeValue((string) ($category['budget_category_type'] ?? '')) !== $payload['request_type']) {
            $errors[] = 'Budget category does not match the selected request type.';
        }

        if ($workflow === [] || (int) ($workflow['workflow_id'] ?? 0) <= 0) {
            $errors[] = 'No active workflow is available for the selected request type and amount.';
        }

        if ($firstStepId === null) {
            $errors[] = 'The selected workflow has no approval steps.';
        }

            if ($firstStepId !== null && $firstStepAssigneeIds === []) {
                $errors[] = 'The first workflow step does not have an approver user.';
            }

        return $errors;
    }

    private function canViewRequestRecord(array $request): bool
    {
        $ownerUserId = (int) ($request['request_submitted_by'] ?? 0);
        $requestDepartmentId = (int) ($request['department_id'] ?? 0);
        if ($this->rbac->canAccessRequest($ownerUserId, $requestDepartmentId)) {
            return true;
        }

        $currentUserId = (int) ($_SESSION['auth']['user_id'] ?? 0);
        $requestId = (int) ($request['request_id'] ?? 0);
        if ($this->model->isUserAssignedToRequest($requestId, $currentUserId)) {
            return true;
        }

        $workflowId = (int) ($request['workflow_id'] ?? 0);
        $currentStepId = (int) ($request['request_current_step_id'] ?? 0);
        if ($workflowId > 0 && $currentStepId > 0) {
            $step = $this->model->getCurrentRequestStepDefinition($requestId, $currentStepId, $workflowId);
            if (is_array($step)) {
                $approverType = strtolower(trim((string) ($step['step_approver_type'] ?? '')));
                $approverRole = strtolower(trim((string) ($step['step_approver_role'] ?? '')));

                if ($approverType === 'department_head' && $this->rbac->isDepartmentHead()) {
                    return true;
                }

                if ($approverRole !== '' && $approverRole === $this->rbac->role()) {
                    return true;
                }

                if ($approverType === 'manager' && $this->rbac->isManager()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function deriveStepProgressStatus(array $request, array $step): string
    {
        $requestStatus = strtolower(trim((string) ($request['request_status'] ?? 'pending')));
        $currentStepId = (int) ($request['request_current_step_id'] ?? 0);
        $stepId = (int) ($step['step_id'] ?? 0);
        $latestAction = strtolower(trim((string) ($step['latest_action'] ?? '')));
        $assignmentStatus = strtolower(trim((string) ($step['request_step_status'] ?? '')));

        if ($latestAction === 'reject' || ($assignmentStatus === 'rejected' && $requestStatus === 'rejected')) {
            return 'rejected';
        }

        if ($latestAction === 'approve' || in_array($assignmentStatus, ['approved', 'auto_approved'], true)) {
            return 'approved';
        }

        if ($requestStatus === 'approved') {
            return 'approved';
        }

        if ($requestStatus === 'rejected') {
            return $stepId === $currentStepId ? 'rejected' : 'pending';
        }

        if ($requestStatus === 'pending' && $stepId === $currentStepId) {
            return 'in_progress';
        }

        return 'pending';
    }

    private function emitAttachmentDownload(string $binaryPayload, string $fileName, string $mimeType, bool $inline): void
    {
        $safeName = basename($fileName);
        if ($safeName === '') {
            $safeName = 'attachment.bin';
        }

        header('Content-Type: ' . ($mimeType !== '' ? $mimeType : 'application/octet-stream'));
        header('Content-Length: ' . (string) strlen($binaryPayload));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . addslashes($safeName) . '"');
        echo $binaryPayload;
        exit;
    }

    public function list(): void
    {
        $this->ensureExpenseAccess();

        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 15;
        $requestScopeParam = $_GET['request_scope'] ?? '';
        $defaultRequestScope = "all";

        if ($requestScopeParam !== '' && !in_array($requestScopeParam, ['all', 'my_requests', 'others'], true)) {
            $requestScopeParam = '';
        }

        $canFilterByDepartment = $this->rbac->role() === 'finance';
        $selectedDepartment = $canFilterByDepartment ? (string) ($_GET['department'] ?? '') : '';

        $filters = [
            'search' => $_GET['search'] ?? '',
            'request_scope' => $requestScopeParam !== '' ? $requestScopeParam : $defaultRequestScope,
            'type' => $_GET['type'] ?? '',
            'department' => $selectedDepartment,
            'status' => $_GET['status'] ?? 'pending',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];

        $data = $this->model->getExpenses($filters, $page, $perPage);

        if (!empty($_GET['download'])) {
            $exportData = $this->model->getExpenses($filters, max(1, (int) ($data['total'] ?? 0)), 0);
            $exportRows = [];
            foreach (($exportData['expenses'] ?? []) as $expenseRow) {
                $exportRows[] = [
                    (string) ($expenseRow['request_title'] ?? ''),
                    (string) ($expenseRow['department_name'] ?? '—'),
                    ((string) ($expenseRow['request_currency'] ?? 'INR')) . ' ' . number_format((float) ($expenseRow['request_amount'] ?? 0), 2),
                    match ((string) ($expenseRow['request_type'] ?? '')) {
                        'expense' => 'Expense',
                        'purchase' => 'Purchase',
                        default => ucfirst((string) ($expenseRow['request_type'] ?? '')),
                    },
                    ucfirst((string) ($expenseRow['request_status'] ?? 'pending')),
                    (string) ($expenseRow['submitter_name'] ?? '—'),
                    !empty($expenseRow['request_submitted_at']) ? date('d M Y', strtotime((string) $expenseRow['request_submitted_at'])) : '—',
                ];
            }

            $exportService = new SpreadsheetExportService();
            $exportService->streamXlsx(
                'expenses-' . date('YmdHis') . '.xlsx',
                ['Title', 'Department', 'Amount', 'Type', 'Status', 'Submitted By', 'Date'],
                $exportRows,
                'Expenses'
            );
        }
        $editableRequestIds = [];
        if (!empty($data['expenses']) && is_array($data['expenses'])) {
            $requestIds = [];
            foreach ($data['expenses'] as $expenseRow) {
                $requestId = (int) ($expenseRow['request_id'] ?? 0);
                if ($requestId > 0) {
                    $requestIds[] = $requestId;
                }
            }
            $editableRequestIds = $this->model->getEditablePendingRequestIdsForOwner((int) ($_SESSION['auth']['user_id'] ?? 0), $requestIds);
        }
        $departments = $canFilterByDepartment ? $this->departmentModel()->getAllDepartments() : [];

        extract(array_merge($data, [
            'filters' => $filters,
            'currentPage' => $page,
            'perPage' => $perPage,
            'departments' => $departments,
            'editableRequestIds' => $editableRequestIds,
            'canCreateExpense' => true,
            'defaultRequestScope' => $defaultRequestScope,
            'canFilterByDepartment' => $canFilterByDepartment,
        ]));

        require ROOT_PATH . '/views/templates/app_layout.php';
        renderAppLayoutStart(['activeMenu' => 'expense-list', 'pageTitle' => 'Expenses']);
        require ROOT_PATH . '/views/ExpenseManagement/expense_list.php';
        renderAppLayoutEnd();
    }

    public function create(): void
    {
        $this->ensureExpenseAccess();

        $oldInput = isset($_SESSION['expense_create_old_input']) && is_array($_SESSION['expense_create_old_input'])
            ? $_SESSION['expense_create_old_input']
            : [];
        unset($_SESSION['expense_create_old_input']);

        $pageTitle = 'Create Expense Request - Expense Register';
        $pageStyles = ['assets/css/app.css'];
        $activeMenu = 'expense-list';
        $formTitle = 'Create Expense Request';
        $formAction = buildCleanRouteUrl('expenses/create');
        $submitLabel = 'Submit Request';
        $formError = trim((string) ($_GET['error'] ?? ''));
        $attachmentMaxSizeMb = $this->fileUploadMaxSizeMb();

        $requestTypes = $this->requestTypeOptions();
        $priorityOptions = $this->priorityOptions();
        $departmentId = $this->resolveAuthenticatedDepartmentId();
        if ($departmentId <= 0) {
            flash_error('Your account is not linked to a department. Please contact HR/Admin.');
            header('Location: ' . buildCleanRouteUrl('expenses'));
            exit;
        }
        $budgetCategories = $this->budgetCategoryModel()->getSelectableCategories();

        require ROOT_PATH . '/views/templates/app_layout.php';
        renderAppLayoutStart(['activeMenu' => $activeMenu, 'pageTitle' => $pageTitle]);
        require ROOT_PATH . '/views/ExpenseManagement/expense_creation.php';
        renderAppLayoutEnd();
    }

    public function store(): void
    {
        $this->ensureExpenseAccess();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . buildCleanRouteUrl('expenses/create'));
            exit;
        }

        $payload = $this->normalizeCreatePayload($_POST);
        $payload['department_id'] = $this->resolveAuthenticatedDepartmentId();
        $categoryModel = $this->budgetCategoryModel();
        $category = $payload['budget_category_id'] > 0 ? $categoryModel->getCategoryById($payload['budget_category_id']) : null;
        $workflowSelection = $this->resolveWorkflowSelection(
            $payload['request_type'],
            $payload['request_amount'],
            (int) $payload['department_id'],
            (int) ($_SESSION['auth']['user_id'] ?? 0)
        );
        $workflow = is_array($workflowSelection[0] ?? null) ? $workflowSelection[0] : [];
        $firstStepId = isset($workflowSelection[1]) && is_int($workflowSelection[1]) ? $workflowSelection[1] : null;
            $firstStepAssigneeIds = isset($workflowSelection[2]) && is_array($workflowSelection[2]) ? $workflowSelection[2] : [];
                $validationErrors = $this->validateCreatePayload($payload, is_array($category) ? $category : [], $workflow, $firstStepId, $firstStepAssigneeIds);

        if (!empty($validationErrors)) {
            $_SESSION['expense_create_old_input'] = $payload;
            flash_error(implode(' ', $validationErrors));
            header('Location: ' . buildCleanRouteUrl('expenses/create'));
            exit;
        }

        $requestData = [
            'request_reference_no' => $payload['request_reference_no'],
            'request_type' => $payload['request_type'],
            'request_title' => $payload['request_title'],
            'request_description' => $payload['request_description'] !== '' ? $payload['request_description'] : null,
            'request_amount' => $payload['request_amount'],
            'request_currency' => 'INR',
            'department_id' => $payload['department_id'],
            'request_category' => (string) ($category['budget_category_name'] ?? ''),
            'budget_category_id' => $payload['budget_category_id'],
            'workflow_id' => (int) ($workflow['workflow_id'] ?? 0),
            'request_current_step_id' => $firstStepId,
                'request_step_assigned_to_ids' => $firstStepAssigneeIds,
                'request_step_assigned_to' => (int) ($firstStepAssigneeIds[0] ?? 0),
            'request_submitted_by' => (int) ($_SESSION['auth']['user_id'] ?? 0),
            'request_status' => 'pending',
            'request_priority' => $payload['request_priority'],
            'request_notes' => $payload['request_notes'] !== '' ? $payload['request_notes'] : null,
            'request_submitted_at' => date('Y-m-d H:i:s'),
        ];

        $attachmentPayloads = [];
        $uploadedFiles = $this->normalizeUploadedFiles('attachment_file');
        $hasAttachment = false;
        foreach ($uploadedFiles as $file) {
            if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $hasAttachment = true;
                break;
            }
        }

        if (!$hasAttachment) {
            $_SESSION['expense_create_old_input'] = $payload;
            flash_error('Please add a attachment to submit a request');
            header('Location: ' . buildCleanRouteUrl('expenses/create'));
            exit;
        }
		$attachmentTypes = isset($payload['attachment_types']) && is_array($payload['attachment_types']) ? $payload['attachment_types'] : [];
        $fileIndex = 0;
        foreach ($uploadedFiles as $file) {
            $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                $fileIndex++;
                continue;
            }

            if ($uploadError !== UPLOAD_ERR_OK) {
                $_SESSION['expense_create_old_input'] = $payload;
                flash_error('Failed to upload attachment. Please try again.');
                header('Location: ' . buildCleanRouteUrl('expenses/create'));
                exit;
            }

            $attachmentType = isset($attachmentTypes[$fileIndex]) ? strtolower(trim((string) $attachmentTypes[$fileIndex])) : '';
            if ($attachmentType === '') {
                $_SESSION['expense_create_old_input'] = $payload;
                flash_error('Please select attachment type for each file.');
                header('Location: ' . buildCleanRouteUrl('expenses/create'));
                exit;
            }

            try {
                $attachmentPayloads[] = $this->buildAttachmentPayload($file, $attachmentType);
            } catch (Throwable $error) {
                $_SESSION['expense_create_old_input'] = $payload;
                flash_error($error->getMessage());
                header('Location: ' . buildCleanRouteUrl('expenses/create'));
                exit;
            }

            $fileIndex++;
        }

        $requestId = $this->model->createRequest($requestData, $attachmentPayloads);
        if ($requestId <= 0) {
            $_SESSION['expense_create_old_input'] = $payload;
            flash_error('Failed to create expense request.');
            header('Location: ' . buildCleanRouteUrl('expenses/create'));
            exit;
        }

        RbacService::audit('request_create', [
            'request_id' => $requestId,
            'request_type' => $requestData['request_type'],
        ]);

        $requestRecord = $this->model->getRequestReviewDetails($requestId);
        if (is_array($requestRecord)) {
                $requestTypeLabel = match (strtolower(trim((string) ($requestRecord['request_type'] ?? '')))) {
                    'expense' => 'Expense',
                    'purchase' => 'Purchase',
                default => ucfirst((string) ($requestRecord['request_type'] ?? 'Request')),
            };
            $requestNo = (string) ($requestRecord['request_reference_no'] ?? $requestData['request_reference_no']);
            $requestAmount = number_format((float) ($requestRecord['request_amount'] ?? 0), 2, '.', '');
            $requestCurrency = (string) ($requestRecord['request_currency'] ?? $requestData['request_currency']);
            $requestBudgetHead = trim((string) ($requestRecord['budget_category_name'] ?? ''));
            if ($requestBudgetHead === '') {
                $requestBudgetHead = trim((string) ($requestRecord['request_category'] ?? ''));
            }
            $requestDescription = (string) ($requestRecord['request_description'] ?? '');
            $requesterEmail = trim((string) ($requestRecord['submitter_email'] ?? ''));
            $requesterName = trim((string) ($requestRecord['submitter_name'] ?? ''));
            $requestLink = buildCleanRouteUrl('expenses/review', ['id' => $requestId]);
            $mailService = new MailService();

            // Request submission email to requester removed - now requester gets CC on approver email

            $approverNotifications = $this->model->getCurrentStepApproverNotifications($requestId);
            foreach ($approverNotifications as $approverNotification) {
                $approverEmail = trim((string) ($approverNotification['approver_email'] ?? ''));
                if ($approverEmail === '') {
                    continue;
                }

                $sent = $mailService->sendRequestActionRequiredEmail(
                    $approverEmail,
                    trim((string) ($approverNotification['approver_name'] ?? 'Approver')),
                    $requesterName !== '' ? $requesterName : 'Employee',
                    '',
                    $requestTypeLabel,
                    $requestNo,
                    $requestCurrency,
                    $requestAmount,
                    $requestBudgetHead !== '' ? $requestBudgetHead : '—',
                    $requestDescription,
                    $requestLink,
                    (int) ($approverNotification['approval_timeout'] ?? 24),
                    true,
                    $requesterEmail // CC to requester
                );

                if (!$sent) {
                    error_log('Failed to send action required email for request ' . $requestId . ' to approver ' . ((int) ($approverNotification['approver_id'] ?? 0)));
                }
            }
        }

        flash_success('Expense request created successfully.');
        header('Location: ' . buildCleanRouteUrl('expenses'));
        exit;
    }

    public function review(): void
    {
        $this->ensureAuthenticated();

        $requestId = (int) ($_GET['id'] ?? 0);
        if ($requestId <= 0) {
            flash_error('Invalid request id.');
            header('Location: ' . buildCleanRouteUrl('expenses'));
            exit;
        }

        $request = $this->model->getRequestReviewDetails($requestId);
        if (!is_array($request)) {
            flash_error('Request not found.');
            header('Location: ' . buildCleanRouteUrl('expenses'));
            exit;
        }

        if (!$this->canViewRequestRecord($request)) {
            header('Location: ' . buildCleanRouteUrl('forbidden'));
            exit;
        }

        $currentUserId = (int) ($_SESSION['auth']['user_id'] ?? 0);
        $pendingAssignment = $this->model->getPendingAssignmentForUser($requestId, $currentUserId);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $requestedAction = strtolower(trim((string) ($_POST['request_action'] ?? '')));
            $actionComment = trim((string) ($_POST['action_comment'] ?? ''));
            $reassignTo = (int) ($_POST['reassign_to'] ?? 0);

            if ($requestedAction === '') {
                flash_error('Please choose an approval action.');
                header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
                exit;
            }

            if (!is_array($pendingAssignment)) {
                flash_error('You are not assigned to act on this request.');
                header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
                exit;
            }

            if ($requestedAction === 'reassign' && !$this->rbac->isDepartmentHead()) {
                if (!$this->rbac->isManager()) {
                    flash_error('Only manager or department head can reassign a request.');
                    header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
                    exit;
                }
            }

            if (($requestedAction === 'reject' || $requestedAction === 'reassign') && $actionComment === '') {
                flash_error('Provide the reason in the required field');
                header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
                exit;
            }

            $rejectionStepTitle = 'Current Step';
            if ($requestedAction === 'reject') {
                $currentStepDefinition = $this->model->getCurrentRequestStepDefinition(
                    $requestId,
                    (int) ($request['request_current_step_id'] ?? 0),
                    (int) ($request['workflow_id'] ?? 0)
                );
                if (is_array($currentStepDefinition)) {
                    $resolvedStepTitle = trim((string) ($currentStepDefinition['step_name'] ?? ''));
                    if ($resolvedStepTitle !== '') {
                        $rejectionStepTitle = $resolvedStepTitle;
                    }
                }
            }

            try {
                $result = $this->model->processRequestAction(
                    $requestId,
                    $currentUserId,
                    $requestedAction,
                    $actionComment !== '' ? $actionComment : null,
                    $reassignTo > 0 ? $reassignTo : null
                );

                if ($requestedAction === 'approve') {
                    $updatedRequest = $this->model->getRequestReviewDetails($requestId);
                    if (is_array($updatedRequest) && strtolower(trim((string) ($updatedRequest['request_status'] ?? ''))) === 'pending') {
                        $requestTypeLabel = match (strtolower(trim((string) ($updatedRequest['request_type'] ?? '')))) {
                            'expense' => 'Expense',
                            'purchase' => 'Purchase',
                            default => ucfirst((string) ($updatedRequest['request_type'] ?? 'Request')),
                        };
                        $requestNo = (string) ($updatedRequest['request_reference_no'] ?? '');
                        $requestAmount = number_format((float) ($updatedRequest['request_amount'] ?? 0), 2, '.', '');
                        $requestCurrency = (string) ($updatedRequest['request_currency'] ?? 'INR');
                        $requestBudgetHead = trim((string) ($updatedRequest['budget_category_name'] ?? ''));
                        if ($requestBudgetHead === '') {
                            $requestBudgetHead = trim((string) ($updatedRequest['request_category'] ?? ''));
                        }
                        $requestDescription = (string) ($updatedRequest['request_description'] ?? '');
                        $requesterName = trim((string) ($updatedRequest['submitter_name'] ?? ''));
                        $previousActor = (string) ($this->model->getLatestRequestActionActorName($requestId) ?? 'Previous Approver');
                        $requestLink = buildCleanRouteUrl('expenses/review', ['id' => $requestId]);
                        $mailService = new MailService();

                        $approverNotifications = $this->model->getCurrentStepApproverNotifications($requestId);
                        foreach ($approverNotifications as $approverNotification) {
                            $approverEmail = trim((string) ($approverNotification['approver_email'] ?? ''));
                            if ($approverEmail === '') {
                                continue;
                            }

                            $sent = $mailService->sendRequestActionRequiredEmail(
                                $approverEmail,
                                trim((string) ($approverNotification['approver_name'] ?? 'Approver')),
                                $requesterName !== '' ? $requesterName : 'Employee',
                                $previousActor,
                                $requestTypeLabel,
                                $requestNo,
                                $requestCurrency,
                                $requestAmount,
                                $requestBudgetHead !== '' ? $requestBudgetHead : '—',
                                $requestDescription,
                                $requestLink,
                                (int) ($approverNotification['approval_timeout'] ?? 24),
                                false
                            );

                            if (!$sent) {
                                error_log('Failed to send next-step action required email for request ' . $requestId . ' to approver ' . ((int) ($approverNotification['approver_id'] ?? 0)));
                            }
                        }
                    } elseif (is_array($updatedRequest) && strtolower(trim((string) ($updatedRequest['request_status'] ?? ''))) === 'approved') {
                        $requestTypeLabel = match (strtolower(trim((string) ($updatedRequest['request_type'] ?? '')))) {
                            'expense' => 'Expense',
                            'purchase' => 'Purchase',
                            default => ucfirst((string) ($updatedRequest['request_type'] ?? 'Request')),
                        };
                        $requestNo = (string) ($updatedRequest['request_reference_no'] ?? '');
                        $requestAmount = number_format((float) ($updatedRequest['request_amount'] ?? 0), 2, '.', '');
                        $requestCurrency = (string) ($updatedRequest['request_currency'] ?? 'INR');
                        $requestBudgetHead = trim((string) ($updatedRequest['budget_category_name'] ?? ''));
                        if ($requestBudgetHead === '') {
                            $requestBudgetHead = trim((string) ($updatedRequest['request_category'] ?? ''));
                        }
                        $requestDescription = (string) ($updatedRequest['request_description'] ?? '');
                        $requesterName = trim((string) ($updatedRequest['submitter_name'] ?? ''));
                        $requesterEmail = trim((string) ($updatedRequest['submitter_email'] ?? ''));
                        $actorName = (string) ($this->model->getLatestRequestActionActorName($requestId) ?? 'Approver');

                        if ($requesterEmail !== '') {
                            $mailService = new MailService();
                            $sent = $mailService->sendRequestApprovedEmail(
                                $requesterEmail,
                                $requesterName !== '' ? $requesterName : 'Employee',
                                $requestTypeLabel,
                                $requestNo,
                                $actorName,
                                $actionComment !== '' ? $actionComment : null,
                                $requestCurrency,
                                $requestAmount,
                                $requestBudgetHead !== '' ? $requestBudgetHead : '—',
                                $requestDescription,
                                buildCleanRouteUrl('expenses/review', ['id' => $requestId])
                            );

                            if (!$sent) {
                                error_log('Failed to send final request approved email for request ' . $requestId);
                            }
                        }
                    }
                }

                if ($requestedAction === 'reject') {
                    $updatedRequest = $this->model->getRequestReviewDetails($requestId);
                    if (is_array($updatedRequest) && strtolower(trim((string) ($updatedRequest['request_status'] ?? ''))) === 'rejected') {
                        if ($rejectionStepTitle === 'Current Step') {
                            $fallbackStepTitle = $this->model->getLatestRequestActionStepTitle($requestId, 'reject');
                            if (is_string($fallbackStepTitle) && trim($fallbackStepTitle) !== '') {
                                $rejectionStepTitle = trim($fallbackStepTitle);
                            }
                        }

                        $requestTypeLabel = match (strtolower(trim((string) ($updatedRequest['request_type'] ?? '')))) {
                            'expense' => 'Expense',
                            'purchase' => 'Purchase',
                            default => ucfirst((string) ($updatedRequest['request_type'] ?? 'Request')),
                        };
                        $requestNo = (string) ($updatedRequest['request_reference_no'] ?? '');
                        $requesterName = trim((string) ($updatedRequest['submitter_name'] ?? ''));
                        $requesterEmail = trim((string) ($updatedRequest['submitter_email'] ?? ''));
                        $actorName = (string) ($this->model->getLatestRequestActionActorName($requestId) ?? 'Approver');

                        if ($requesterEmail !== '') {
                            $mailService = new MailService();
                            $sent = $mailService->sendRequestRejectedEmail(
                                $requesterEmail,
                                $requesterName !== '' ? $requesterName : 'Employee',
                                $requestTypeLabel,
                                $requestNo,
                                $actorName,
                                $rejectionStepTitle,
                                $actionComment !== '' ? $actionComment : null,
                                buildCleanRouteUrl('expenses/review', ['id' => $requestId])
                            );

                            if (!$sent) {
                                error_log('Failed to send request rejected email for request ' . $requestId);
                            }
                        }
                    }
                }

                if ($requestedAction === 'reassign') {
                    $updatedRequest = $this->model->getRequestReviewDetails($requestId);
                    if (is_array($updatedRequest)) {
                        $requestNo = (string) ($updatedRequest['request_reference_no'] ?? '');
                        $requestLink = buildCleanRouteUrl('expenses/review', ['id' => $requestId]);
                        $userModel = new UserModel();
                        $newApprover = $reassignTo > 0 ? $userModel->getUserById($reassignTo) : null;
                        $actorUser = $userModel->getUserById($currentUserId);
                        $newApproverEmail = trim((string) ($newApprover['user_email'] ?? ''));

                        if ($newApproverEmail !== '') {
                            $mailService = new MailService();
                            $sent = $mailService->sendTicketReassignmentEmail(
                                $newApproverEmail,
                                trim((string) ($newApprover['user_name'] ?? 'Approver')),
                                trim((string) ($actorUser['user_name'] ?? 'Department Head')),
                                $requestNo,
                                $actionComment !== '' ? $actionComment : null,
                                $requestLink
                            );

                            if (!$sent) {
                                error_log('Failed to send ticket reassignment email for request ' . $requestId . ' to user ' . $reassignTo);
                            }
                        }
                    }
                }

                flash_success((string) ($result['message'] ?? 'Request updated successfully.'));
                header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
                exit;
            } catch (Throwable $error) {
                flash_error($error->getMessage());
                header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
                exit;
            }
        }

        $attachments = $this->model->getAttachmentsByRequestId($requestId);
        $actionHistory = $this->model->getRequestActionHistory($requestId);
        $workflowProgress = $this->model->getWorkflowProgressForRequest($requestId, (int) ($request['workflow_id'] ?? 0));
        foreach ($workflowProgress as $index => $step) {
            $workflowProgress[$index]['progress_status'] = $this->deriveStepProgressStatus($request, $step);
        }

        $isOwnRequest = (int) ($request['request_submitted_by'] ?? 0) === $currentUserId;
        $canTakeAction = is_array($pendingAssignment);
        $canReassignRequest = $canTakeAction && ($this->rbac->isDepartmentHead() || $this->rbac->isManager());
        $reassignableUsers = $canReassignRequest
            ? $this->model->getCompanyUsersForReassignment($currentUserId)
            : [];
        $actionFormUrl = buildCleanRouteUrl('expenses/review', ['id' => $requestId]);

        $pageTitle = 'Expense Request Details - Expense Register';
        $activeMenu = 'expense-list';

        require ROOT_PATH . '/views/templates/app_layout.php';
        renderAppLayoutStart([
            'activeMenu' => $activeMenu,
            'pageTitle' => $pageTitle,
        ]);
        require ROOT_PATH . '/views/ExpenseManagement/expense_review.php';
        renderAppLayoutEnd();
    }

    public function downloadAttachment(): void
    {
        $this->streamAttachment(false);
    }

    public function edit(): void
    {
        $this->ensureExpenseAccess();

        $requestId = (int) ($_GET['id'] ?? 0);
        if ($requestId <= 0) {
            flash_error('Invalid request id.');
            header('Location: ' . buildCleanRouteUrl('expenses'));
            exit;
        }

        $currentUserId = (int) ($_SESSION['auth']['user_id'] ?? 0);
        $request = $this->model->getRequestById($requestId);
        if (!is_array($request) || (int) ($request['request_submitted_by'] ?? 0) !== $currentUserId) {
            header('Location: ' . buildCleanRouteUrl('forbidden'));
            exit;
        }

        if (!$this->model->canOwnerEditPendingRequest($requestId, $currentUserId)) {
            flash_error('The request is under Approval Process');
            header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
            exit;
        }

        $oldInput = isset($_SESSION['expense_edit_old_input']) && is_array($_SESSION['expense_edit_old_input'])
            ? $_SESSION['expense_edit_old_input']
            : [];
        unset($_SESSION['expense_edit_old_input']);

        if ($oldInput === []) {
            $oldInput = [
                'request_reference_no' => (string) ($request['request_reference_no'] ?? ''),
                'request_title' => (string) ($request['request_title'] ?? ''),
                'request_type' => $this->normalizeRequestTypeValue((string) ($request['request_type'] ?? 'expense')),
                'request_amount' => (string) ($request['request_amount'] ?? ''),
                'budget_category_id' => (int) ($request['budget_category_id'] ?? 0),
                'request_priority' => (string) ($request['request_priority'] ?? 'low'),
                'request_description' => (string) ($request['request_description'] ?? ''),
                'request_notes' => (string) ($request['request_notes'] ?? ''),
                'attachment_types' => [],
            ];
        }

        $pageTitle = 'Edit Expense Request - Expense Register';
        $activeMenu = 'expense-list';
        $formTitle = 'Edit Expense Request';
        $formAction = buildCleanRouteUrl('expenses/edit', ['id' => $requestId]);
        $submitLabel = 'Update Request';
        $formError = trim((string) ($_GET['error'] ?? ''));
        $attachmentMaxSizeMb = $this->fileUploadMaxSizeMb();
        $requestTypes = $this->requestTypeOptions();
        $priorityOptions = $this->priorityOptions();
        $budgetCategories = $this->budgetCategoryModel()->getSelectableCategories();

        require ROOT_PATH . '/views/templates/app_layout.php';
        renderAppLayoutStart(['activeMenu' => $activeMenu, 'pageTitle' => $pageTitle]);
        require ROOT_PATH . '/views/ExpenseManagement/expense_creation.php';
        renderAppLayoutEnd();
    }

    public function update(): void
    {
        $this->ensureExpenseAccess();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . buildCleanRouteUrl('expenses'));
            exit;
        }

        $requestId = (int) ($_GET['id'] ?? 0);
        if ($requestId <= 0) {
            flash_error('Invalid request id.');
            header('Location: ' . buildCleanRouteUrl('expenses'));
            exit;
        }

        $currentUserId = (int) ($_SESSION['auth']['user_id'] ?? 0);
        $existingRequest = $this->model->getRequestById($requestId);
        if (!is_array($existingRequest) || (int) ($existingRequest['request_submitted_by'] ?? 0) !== $currentUserId) {
            header('Location: ' . buildCleanRouteUrl('forbidden'));
            exit;
        }

        if (!$this->model->canOwnerEditPendingRequest($requestId, $currentUserId)) {
            flash_error('The request is under Approval Process');
            header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
            exit;
        }

        $payload = $this->normalizeCreatePayload($_POST);
        $payload['department_id'] = (int) ($existingRequest['department_id'] ?? 0);

        $categoryModel = $this->budgetCategoryModel();
        $category = $payload['budget_category_id'] > 0 ? $categoryModel->getCategoryById($payload['budget_category_id']) : null;
        $workflowSelection = $this->resolveWorkflowSelection(

            $payload['request_type'],
            $payload['request_amount'],

            (int) $payload['department_id'],
            $currentUserId
        );
        $workflow = is_array($workflowSelection[0] ?? null) ? $workflowSelection[0] : [];
        $firstStepId = isset($workflowSelection[1]) && is_int($workflowSelection[1]) ? $workflowSelection[1] : null;
        $firstStepAssigneeIds = isset($workflowSelection[2]) && is_array($workflowSelection[2]) ? $workflowSelection[2] : [];
        $validationErrors = $this->validateCreatePayload($payload, is_array($category) ? $category : [], $workflow, $firstStepId, $firstStepAssigneeIds);

        if (!empty($validationErrors)) {
            $_SESSION['expense_edit_old_input'] = $payload;
            flash_error(implode(' ', $validationErrors));
            header('Location: ' . buildCleanRouteUrl('expenses/edit', ['id' => $requestId]));
            exit;
        }

        $requestData = [
            'request_reference_no' => $payload['request_reference_no'],
            'request_type' => $payload['request_type'],
            'request_title' => $payload['request_title'],
            'request_description' => $payload['request_description'] !== '' ? $payload['request_description'] : null,
            'request_amount' => $payload['request_amount'],
            'request_currency' => 'INR',
            'department_id' => $payload['department_id'],
            'request_category' => (string) ($category['budget_category_name'] ?? ''),
            'budget_category_id' => $payload['budget_category_id'],
            'workflow_id' => (int) ($workflow['workflow_id'] ?? 0),
            'request_current_step_id' => $firstStepId,
            'request_priority' => $payload['request_priority'],
            'request_notes' => $payload['request_notes'] !== '' ? $payload['request_notes'] : null,
        ];

        $attachmentPayloads = [];
        $attachmentTypes = isset($payload['attachment_types']) && is_array($payload['attachment_types']) ? $payload['attachment_types'] : [];
        $fileIndex = 0;
        foreach ($this->normalizeUploadedFiles('attachment_file') as $file) {
            $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                $fileIndex++;
                continue;
            }

            if ($uploadError !== UPLOAD_ERR_OK) {
                $_SESSION['expense_edit_old_input'] = $payload;
                flash_error('Failed to upload attachment. Please try again.');
                header('Location: ' . buildCleanRouteUrl('expenses/edit', ['id' => $requestId]));
                exit;
            }

            $attachmentType = isset($attachmentTypes[$fileIndex]) ? strtolower(trim((string) $attachmentTypes[$fileIndex])) : '';
            if ($attachmentType === '') {
                $_SESSION['expense_edit_old_input'] = $payload;
                flash_error('Please select attachment type for each file.');
                header('Location: ' . buildCleanRouteUrl('expenses/edit', ['id' => $requestId]));
                exit;
            }

            try {
                $attachmentPayloads[] = $this->buildAttachmentPayload($file, $attachmentType);
            } catch (Throwable $error) {
                $_SESSION['expense_edit_old_input'] = $payload;
                flash_error($error->getMessage());
                header('Location: ' . buildCleanRouteUrl('expenses/edit', ['id' => $requestId]));
                exit;
            }

            $fileIndex++;
        }

        $updated = $this->model->updateRequestBeforeFirstApproval(
            $requestId,
            $requestData,
            $firstStepAssigneeIds,
            $attachmentPayloads
        );

        if (!$updated) {
            $_SESSION['expense_edit_old_input'] = $payload;
            flash_error('Failed to update expense request.');
            header('Location: ' . buildCleanRouteUrl('expenses/edit', ['id' => $requestId]));
            exit;
        }

        flash_success('Expense request updated successfully.');
        header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
        exit;
    }

    public function viewAttachment(): void
    {
        $this->streamAttachment(true);
    }

    private function streamAttachment(bool $inline): void
    {
        $this->ensureAuthenticated();

        $attachmentId = (int) ($_GET['attachment_id'] ?? 0);
        $requestId = (int) ($_GET['request_id'] ?? 0);
        if ($attachmentId <= 0 || $requestId <= 0) {
            flash_error('Invalid attachment request.');
            header('Location: ' . buildCleanRouteUrl('expenses'));
            exit;
        }

        $request = $this->model->getRequestReviewDetails($requestId);
        if (!is_array($request)) {
            flash_error('Request not found.');
            header('Location: ' . buildCleanRouteUrl('expenses'));
            exit;
        }

        if (!$this->canViewRequestRecord($request)) {
            header('Location: ' . buildCleanRouteUrl('forbidden'));
            exit;
        }

        $attachment = $this->model->getAttachmentById($attachmentId, $requestId);
        if (!is_array($attachment)) {
            flash_error('Attachment not found.');
            header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
            exit;
        }

        $filePath = (string) ($attachment['attachment_file_path'] ?? '');
        if ($filePath === '') {
            flash_error('Attachment file path not found.');
            header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
            exit;
        }

        $absolutePath = $filePath;
        if (!str_starts_with($filePath, '/')) {
            $absolutePath = ROOT_PATH . '/' . ltrim($filePath, '/');
        }

        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            flash_error('Attachment file is not available on the server.');
            header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
            exit;
        }

        $fileContents = file_get_contents($absolutePath);
        if ($fileContents === false) {
            flash_error('Failed to read attachment file.');
            header('Location: ' . buildCleanRouteUrl('expenses/review', ['id' => $requestId]));
            exit;
        }

        $this->emitAttachmentDownload(
            $fileContents,
            (string) ($attachment['attachment_file_name'] ?? 'attachment.bin'),
            (string) ($attachment['attachment_mime_type'] ?? 'application/octet-stream'),
            $inline
        );
    }
}