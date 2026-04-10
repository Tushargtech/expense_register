<?php

class ExpenseController
{
    private function ensureAuthenticated(): void
    {
        if (empty($_SESSION['auth']['is_logged_in'])) {
            $_SESSION['auth_error'] = 'Please login to continue.';
            header('Location: ?route=dashboard');
            exit;
        }
    }

    private function normalizeExpensePayload(array $source): array
    {
        return [
            'request_title' => trim((string) ($source['request_title'] ?? '')),
            'request_description' => trim((string) ($source['request_description'] ?? '')),
            'request_amount' => (float) ($source['request_amount'] ?? 0),
            'request_currency' => strtoupper(trim((string) ($source['request_currency'] ?? 'INR'))),
            'department_id' => (int) ($source['department_id'] ?? 0),
            'budget_category_id' => (int) ($source['budget_category_id'] ?? 0),
            'request_priority' => strtolower(trim((string) ($source['request_priority'] ?? 'medium'))),
            'request_notes' => trim((string) ($source['request_notes'] ?? '')),
        ];
    }

    private function isValidExpensePayload(array $payload): bool
    {
        if (empty($payload['request_title']) || strlen($payload['request_title']) > 255) {
            return false;
        }

        if (empty($payload['request_description']) || strlen($payload['request_description']) > 1000) {
            return false;
        }

        if ($payload['request_amount'] <= 0 || $payload['request_amount'] > 999999999.99) {
            return false;
        }

        if (!in_array(strtoupper($payload['request_currency']), ['INR', 'USD', 'EUR', 'GBP'], true)) {
            return false;
        }

        if ($payload['department_id'] <= 0) {
            return false;
        }

        if ($payload['budget_category_id'] <= 0) {
            return false;
        }

        if (!in_array($payload['request_priority'], ['low', 'medium', 'high'], true)) {
            return false;
        }

        if (strlen($payload['request_notes']) > 500) {
            return false;
        }

        return true;
    }

    private function canUserActOnRequest(array $request, int $userId): bool
    {
        $status = strtolower((string) ($request['request_status'] ?? ''));
        if ($status !== 'pending') {
            return false;
        }

        $currentStepId = $request['request_current_step_id'] ?? null;
        if ($currentStepId === null) {
            return false; // No workflow or completed
        }

        $expenseModel = new ExpenseModel();
        $steps = $expenseModel->getRequestSteps($request['request_id']);
        foreach ($steps as $step) {
            if ((int) $step['workflow_step_id'] === (int) $currentStepId) {
                return (int) $step['request_step_assigned_to'] === $userId;
            }
        }

        return false;
    }

    public function list(): void
    {
        $this->ensureAuthenticated();

        $expenseModel = new ExpenseModel();
        $departmentModel = new DepartmentModel();

        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'department_id' => (int) ($_GET['department_id'] ?? 0),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];

        $perPage = 10;
        $currentPage = max(1, (int) ($_GET['page'] ?? 1));
        $totalRequests = $expenseModel->countAllExpenseRequests($filters);
        $totalPages = max(1, (int) ceil($totalRequests / $perPage));
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }
        $offset = ($currentPage - 1) * $perPage;

        $requests = $expenseModel->getAllExpenseRequests($filters, $perPage, $offset);
        $departments = $departmentModel->getAllDepartments();

        $pageTitle = 'Expense Requests - Expense Register';
        $pageStyles = ['assets/css/dashboard.css', 'assets/css/list.css'];
        $envConfig = $GLOBALS['envConfig'] ?? [];
        $userName = (string) ($_SESSION['auth']['name'] ?? 'User');
        $activeMenu = 'expense-list';

        require ROOT_PATH . '/views/templates/header.php';
        require ROOT_PATH . '/views/templates/navbar.php';
        require ROOT_PATH . '/views/templates/sidebar.php';
        require ROOT_PATH . '/views/module-1/expense_list.php';
        require ROOT_PATH . '/views/templates/footer.php';
    }

    public function reviewActions(): void
    {
        $this->ensureAuthenticated();

        $currentUserId = (int) ($_SESSION['auth']['user_id'] ?? 0);
        $expenseModel = new ExpenseModel();
        $departmentModel = new DepartmentModel();

        $requests = $expenseModel->getPendingApprovalRequests($currentUserId);
        $departments = $departmentModel->getAllDepartments();

        $pageTitle = 'Expense Review & Actions - Expense Register';
        $pageStyles = ['assets/css/dashboard.css', 'assets/css/list.css'];
        $envConfig = $GLOBALS['envConfig'] ?? [];
        $userName = (string) ($_SESSION['auth']['name'] ?? 'User');
        $activeMenu = 'expense-review-actions';

        require ROOT_PATH . '/views/templates/header.php';
        require ROOT_PATH . '/views/templates/navbar.php';
        require ROOT_PATH . '/views/templates/sidebar.php';
        require ROOT_PATH . '/views/module-1/expense_review_actions.php';
        require ROOT_PATH . '/views/templates/footer.php';
    }

    public function create(): void
    {
        $this->ensureAuthenticated();

        $departmentModel = new DepartmentModel();
        $budgetCategoryModel = new BudgetCategoryModel();

        $departments = $departmentModel->getAllDepartments();
        $categories = $budgetCategoryModel->getFilteredCategories(['type' => 'expense'], 100, 0);

        $pageTitle = 'Create Expense Request - Expense Register';
        $pageStyles = ['assets/css/dashboard.css', 'assets/css/creation.css'];
        $envConfig = $GLOBALS['envConfig'] ?? [];
        $userName = (string) ($_SESSION['auth']['name'] ?? 'User');
        $activeMenu = 'expense-list';
        $formError = trim((string) ($_GET['error'] ?? ''));
        $isEdit = false;
        $formAction = '?route=expenses/create';
        $formTitle = 'Submit New Expense Request';
        $submitLabel = 'Submit Request';
        $expense = [
            'request_title' => '',
            'request_description' => '',
            'request_amount' => '',
            'request_currency' => 'INR',
            'department_id' => 0,
            'budget_category_id' => 0,
            'request_priority' => 'medium',
            'request_notes' => '',
        ];

        require ROOT_PATH . '/views/templates/header.php';
        require ROOT_PATH . '/views/templates/navbar.php';
        require ROOT_PATH . '/views/templates/sidebar.php';
        require ROOT_PATH . '/views/module-1/expense_create.php';
        require ROOT_PATH . '/views/templates/footer.php';
    }

    public function store(): void
    {
        $this->ensureAuthenticated();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ?route=expenses/create');
            exit;
        }

        $payload = $this->normalizeExpensePayload($_POST);
        if (!$this->isValidExpensePayload($payload)) {
            header('Location: ?route=expenses/create&error=' . urlencode('Please fill all required fields correctly.'));
            exit;
        }

        $actorId = (int) ($_SESSION['auth']['user_id'] ?? 0);
        if ($actorId <= 0) {
            header('Location: ?route=expenses/create&error=' . urlencode('Unable to identify submitting user.'));
            exit;
        }

        $expenseModel = new ExpenseModel();
        $success = $expenseModel->createExpenseRequest($payload, $_FILES['attachment_file'] ?? [], $actorId);

        if ($success) {
            header('Location: ?route=expenses&success=' . urlencode('Expense request submitted successfully.'));
        } else {
            header('Location: ?route=expenses/create&error=' . urlencode('Failed to submit expense request.'));
        }
        exit;
    }

    public function review(): void
    {
        $this->ensureAuthenticated();

        $requestId = (int) ($_GET['id'] ?? 0);
        if ($requestId <= 0) {
            header('Location: ?route=expenses&error=' . urlencode('Invalid expense request id.'));
            exit;
        }

        $expenseModel = new ExpenseModel();
        $request = $expenseModel->getRequestById($requestId);

        if ($request === null) {
            header('Location: ?route=expenses&error=' . urlencode('Expense request not found.'));
            exit;
        }

        $currentUserId = (int) ($_SESSION['auth']['user_id'] ?? 0);
        $canAct = $this->canUserActOnRequest($request, $currentUserId);

        $expenseDetail = [
            'request' => $request,
            'attachments' => $expenseModel->getRequestAttachments($requestId),
            'steps' => $expenseModel->getRequestSteps($requestId),
            'actions' => $expenseModel->getRequestActions($requestId),
            'canAct' => $canAct,
        ];

        $pageTitle = 'Review Expense Request - Expense Register';
        $pageStyles = ['assets/css/dashboard.css', 'assets/css/creation.css'];
        $envConfig = $GLOBALS['envConfig'] ?? [];
        $userName = (string) ($_SESSION['auth']['name'] ?? 'User');
        $activeMenu = 'expense-list';

        require ROOT_PATH . '/views/templates/header.php';
        require ROOT_PATH . '/views/templates/navbar.php';
        require ROOT_PATH . '/views/templates/sidebar.php';
        require ROOT_PATH . '/views/module-1/expense_review.php';
        require ROOT_PATH . '/views/templates/footer.php';
    }

    public function action(): void
    {
        $this->ensureAuthenticated();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ?route=expenses');
            exit;
        }

        $requestId = (int) ($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            header('Location: ?route=expenses&error=' . urlencode('Invalid expense request id.'));
            exit;
        }

        $action = strtolower(trim((string) ($_POST['action'] ?? '')));
        if (!in_array($action, ['approve', 'reject'], true)) {
            header('Location: ?route=expenses/review&id=' . $requestId . '&error=' . urlencode('Invalid action.'));
            exit;
        }

        $actorId = (int) ($_SESSION['auth']['user_id'] ?? 0);
        if ($actorId <= 0) {
            header('Location: ?route=expenses/review&id=' . $requestId . '&error=' . urlencode('Unable to identify acting user.'));
            exit;
        }

        $expenseModel = new ExpenseModel();
        $request = $expenseModel->getRequestById($requestId);
        if ($request === null || !$this->canUserActOnRequest($request, $actorId)) {
            header('Location: ?route=expenses/review&id=' . $requestId . '&error=' . urlencode('You are not authorized to perform this action.'));
            exit;
        }

        $comment = trim((string) ($_POST['action_comment'] ?? ''));
        $success = $expenseModel->saveRequestAction($requestId, $action, $actorId, $comment);

        if ($success) {
            header('Location: ?route=expenses/review&id=' . $requestId . '&success=' . urlencode('Action completed successfully.'));
        } else {
            header('Location: ?route=expenses/review&id=' . $requestId . '&error=' . urlencode('Failed to complete action.'));
        }
        exit;
    }
}
