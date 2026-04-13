<?php

class WorkflowController
{
	private function rbac(): RbacService
	{
		return new RbacService();
	}

	private function ensureWorkflowAccess(): void
	{
		$this->ensureAuthenticated();
		if (!$this->rbac()->canViewWorkflowList()) {
			header('Location: ?route=forbidden&code=rbac_workflow');
			exit;
		}
	}

	private function ensureWorkflowViewAccess(): void
	{
		$this->ensureAuthenticated();
		if (!$this->rbac()->canViewWorkflow()) {
			header('Location: ?route=forbidden&code=rbac_workflow_view');
			exit;
		}
	}

	private function ensureWorkflowCreateAccess(): void
	{
		$this->ensureAuthenticated();
		if (!$this->rbac()->canCreateWorkflow()) {
			header('Location: ?route=forbidden&code=rbac_workflow_create');
			exit;
		}
	}

	private function ensureWorkflowEditAccess(): void
	{
		$this->ensureAuthenticated();
		if (!$this->rbac()->canEditWorkflow()) {
			header('Location: ?route=forbidden&code=rbac_workflow_edit');
			exit;
		}
	}

	private function ensureAuthenticated(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			flash_error('Please login to continue.');
			header('Location: ?route=dashboard');
			exit;
		}
	}

	private function normalizeWorkflowPayload(array $source): array
	{
		$rawMin = trim((string) ($source['workflow_amount_min'] ?? ''));
		$rawMax = trim((string) ($source['workflow_amount_max'] ?? ''));
		$workflowDescription = trim((string) ($source['workflow_description'] ?? ''));
		$workflowType = strtolower(trim((string) ($source['workflow_type'] ?? '')));
		$allowedTypes = ['expense', 'purchase'];
		$normalizedType = in_array($workflowType, $allowedTypes, true)
			? ucfirst($workflowType)
			: '';

		return [
			'workflow_name' => trim((string) ($source['workflow_name'] ?? '')),
			'workflow_description' => $workflowDescription !== '' ? $workflowDescription : null,
			'workflow_type' => $normalizedType,
			'workflow_is_active' => (int) ($source['workflow_is_active'] ?? 1) === 1 ? 1 : 0,
			'workflow_is_default' => (int) ($source['workflow_is_default'] ?? 0) === 1 ? 1 : 0,
			'workflow_amount_min' => $rawMin === '' ? null : (float) $rawMin,
			'workflow_amount_max' => $rawMax === '' ? null : (float) $rawMax,
			'workflow_created_by' => (int) ($_SESSION['auth']['user_id'] ?? 0),
		];
	}

	private function normalizeStepsPayload(array $source): array
	{
		$steps = [];
		$stepNames = isset($source['step_name']) && is_array($source['step_name']) ? $source['step_name'] : [];
		$allowedApproverTypes = ['role', 'user', 'department_head'];

		foreach ($stepNames as $index => $stepNameValue) {
			$stepName = trim((string) $stepNameValue);
			if ($stepName === '') {
				continue;
			}

			$stepOrder = (int) ($source['step_order'][$index] ?? ($index + 1));
			$rawStepMin = trim((string) ($source['step_amount_min'][$index] ?? ''));
			$rawStepMax = trim((string) ($source['step_amount_max'][$index] ?? ''));
			$rawTimeoutHours = trim((string) ($source['step_timeout_hours'][$index] ?? ''));
			$rawApproverUserId = trim((string) ($source['step_approver_user_id'][$index] ?? ''));

			$approverType = trim((string) ($source['step_approver_type'][$index] ?? 'role'));
			if (!in_array($approverType, $allowedApproverTypes, true)) {
				$approverType = 'role';
			}

			$approverRole = trim((string) ($source['step_approver_role'][$index] ?? ''));
			$approverUserId = $rawApproverUserId === '' ? null : (int) $rawApproverUserId;

			if ($approverType === 'role') {
				$approverUserId = null;
			}
			if ($approverType === 'user') {
				$approverRole = '';
			}
			if ($approverType === 'department_head') {
				$approverRole = '';
				$approverUserId = null;
			}

			$steps[] = [
				'step_order' => $stepOrder > 0 ? $stepOrder : ($index + 1),
				'step_name' => $stepName,
				'step_approver_type' => $approverType,
				'step_approver_role' => $approverRole,
				'step_approver_user_id' => $approverUserId,
				'step_amount_min' => $rawStepMin === '' ? null : (float) $rawStepMin,
				'step_amount_max' => $rawStepMax === '' ? null : (float) $rawStepMax,
				'step_is_required' => (int) (($source['step_is_required'][$index] ?? '1') === '1' ? 1 : 0),
				'step_timeout_hours' => $rawTimeoutHours === '' ? null : max(1, (int) $rawTimeoutHours),
			];
		}

		return $steps;
	}

	private function formatStepForForm(array $step): array
	{
		return [
			'step_id' => (int) ($step['step_id'] ?? 0),
			'step_order' => (int) ($step['step_order'] ?? 1),
			'step_name' => (string) ($step['step_name'] ?? ''),
			'step_approver_type' => (string) ($step['step_approver_type'] ?? 'role'),
			'step_approver_role' => (string) ($step['step_approver_role'] ?? ''),
			'step_approver_user_id' => $step['step_approver_user_id'] !== null && $step['step_approver_user_id'] !== '' ? (int) $step['step_approver_user_id'] : 0,
			'step_amount_min' => $step['step_amount_min'] !== null && $step['step_amount_min'] !== '' ? (string) $step['step_amount_min'] : '',
			'step_amount_max' => $step['step_amount_max'] !== null && $step['step_amount_max'] !== '' ? (string) $step['step_amount_max'] : '',
			'step_timeout_hours' => $step['step_timeout_hours'] !== null && $step['step_timeout_hours'] !== '' ? (string) $step['step_timeout_hours'] : '',
			'step_is_required' => (int) ($step['step_is_required'] ?? 1) === 1,
		];
	}

	private function isValidWorkflowPayload(array $workflowData, array $steps): bool
	{
		if ($workflowData['workflow_name'] === '' || $workflowData['workflow_type'] === '') {
			return false;
		}

		if (
			$workflowData['workflow_amount_min'] !== null &&
			$workflowData['workflow_amount_max'] !== null &&
			$workflowData['workflow_amount_min'] > $workflowData['workflow_amount_max']
		) {
			return false;
		}

		if (count($steps) === 0) {
			return false;
		}

		foreach ($steps as $step) {
			if ($step['step_name'] === '') {
				return false;
			}

			if ($step['step_approver_type'] === 'user' && (int) ($step['step_approver_user_id'] ?? 0) <= 0) {
				return false;
			}

			if ($step['step_approver_type'] === 'role' && trim((string) ($step['step_approver_role'] ?? '')) === '') {
				return false;
			}

			if (!in_array((string) ($step['step_approver_type'] ?? ''), ['role', 'user', 'department_head'], true)) {
				return false;
			}

			if (
				$step['step_amount_min'] !== null &&
				$step['step_amount_max'] !== null &&
				$step['step_amount_min'] > $step['step_amount_max']
			) {
				return false;
			}
		}

		return true;
	}

	private function renderForm(array $viewData): void
	{
		extract($viewData, EXTR_SKIP);

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/workflow_creation.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}

	public function list(): void
	{
		$this->ensureWorkflowAccess();

		$workflowModel = new WorkflowModel();

		$filters = [
			'search' => trim((string) ($_GET['search'] ?? '')),
			'status' => trim((string) ($_GET['status'] ?? '')),
			'workflow_type' => trim((string) ($_GET['workflow_type'] ?? '')),
		];

		$perPage = 10;
		$currentPage = max(1, (int) ($_GET['page'] ?? 1));
		$totalWorkflows = $workflowModel->countAllWorkflows($filters);
		$totalPages = max(1, (int) ceil($totalWorkflows / $perPage));

		if ($currentPage > $totalPages) {
			$currentPage = $totalPages;
		}

		$offset = ($currentPage - 1) * $perPage;
		$workflows = $workflowModel->getAllWorkflows($filters, $perPage, $offset);
		$workflowTypes = $workflowModel->getAllWorkflowTypes();

		$pageTitle = 'Workflow List - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/list.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'workflow-list';
		$canCreateWorkflow = $this->rbac()->canCreateWorkflow();
		$canEditWorkflow = $this->rbac()->canEditWorkflow();

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/workflow_list.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}

	public function create(): void
	{
		$this->ensureWorkflowCreateAccess();

		$model = new WorkflowModel();
		$roles = $model->getRoles();
		$users = $model->getActiveUsers();

		$pageTitle = 'Create Workflow - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/creation.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'workflow-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = false;
		$formTitle = 'Create Workflow';
		$formAction = '?route=workflows/create';
		$submitLabel = 'Save Workflow';
		$workflow = [
			'workflow_name' => '',
			'workflow_type' => '',
			'workflow_amount_min' => '',
			'workflow_amount_max' => '',
			'workflow_is_active' => 1,
			'workflow_is_default' => 0,
		];
		$workflowSteps = [
			[
				'step_order' => 1,
				'step_name' => '',
				'step_approver_type' => 'role',
				'step_approver_role' => '',
				'step_approver_user_id' => 0,
				'step_amount_min' => '',
				'step_amount_max' => '',
				'step_timeout_hours' => '',
				'step_is_required' => true,
			],
		];

		$this->renderForm(compact(
			'roles',
			'users',
			'pageTitle',
			'pageStyles',
			'envConfig',
			'userName',
			'activeMenu',
			'formError',
			'isEdit',
			'formTitle',
			'formAction',
			'submitLabel',
			'workflow',
			'workflowSteps'
		));
	}

	public function edit(): void
	{
		$this->ensureWorkflowViewAccess();

		$workflowId = (int) ($_GET['id'] ?? 0);
		if ($workflowId <= 0) {
			flash_error('Invalid workflow id');
			header('Location: ?route=workflows');
			exit;
		}

		$model = new WorkflowModel();
		$workflow = $model->getWorkflowById($workflowId);
		if ($workflow === null) {
			flash_error('Workflow not found');
			header('Location: ?route=workflows');
			exit;
		}

		$roles = $model->getRoles();
		$users = $model->getActiveUsers();
		$steps = $model->getWorkflowStepsByWorkflowId($workflowId);
		$workflowSteps = [];
		foreach ($steps as $step) {
			$workflowSteps[] = $this->formatStepForForm($step);
		}

		if (count($workflowSteps) === 0) {
			$workflowSteps[] = [
				'step_order' => 1,
				'step_name' => '',
				'step_approver_type' => 'role',
				'step_approver_role' => '',
				'step_approver_user_id' => 0,
				'step_amount_min' => '',
				'step_amount_max' => '',
				'step_timeout_hours' => '',
				'step_is_required' => true,
			];
		}

		$pageTitle = 'Edit Workflow - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/creation.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'workflow-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = true;
		$canEditWorkflow = $this->rbac()->canEditWorkflow();
		$formTitle = $canEditWorkflow ? 'Edit Workflow' : 'View Workflow';
		$formAction = '?route=workflows/edit&id=' . $workflowId;
		$submitLabel = $canEditWorkflow ? 'Update Workflow' : 'Back to Workflow List';

		$this->renderForm(compact(
			'roles',
			'users',
			'pageTitle',
			'pageStyles',
			'envConfig',
			'userName',
			'activeMenu',
			'formError',
			'isEdit',
			'formTitle',
			'formAction',
			'submitLabel',
			'canEditWorkflow',
			'workflow',
			'workflowSteps'
		));
	}

	public function store(): void
	{
		$this->ensureWorkflowCreateAccess();

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=workflows/create');
			exit;
		}

		$workflowData = $this->normalizeWorkflowPayload($_POST);
		$steps = $this->normalizeStepsPayload($_POST);

		if (!$this->isValidWorkflowPayload($workflowData, $steps)) {
			flash_error('Please fill required fields and ensure amount ranges are valid.');
			header('Location: ?route=workflows/create');
			exit;
		}

		$model = new WorkflowModel();
		$success = $model->createWorkflow($workflowData, $steps);

		if ($success) {
			RbacService::audit('workflow_create', ['workflow_name' => $workflowData['workflow_name']]);
			flash_success('Workflow created successfully.');
			header('Location: ?route=workflows');
		} else {
			flash_error('Failed to create workflow.');
			header('Location: ?route=workflows/create');
		}

		exit;
	}

	public function update(): void
	{
		$this->ensureWorkflowEditAccess();

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=workflows');
			exit;
		}

		$workflowId = (int) ($_GET['id'] ?? 0);
		if ($workflowId <= 0) {
			flash_error('Invalid workflow id');
			header('Location: ?route=workflows');
			exit;
		}

		$workflowData = $this->normalizeWorkflowPayload($_POST);
		$steps = $this->normalizeStepsPayload($_POST);

		if (!$this->isValidWorkflowPayload($workflowData, $steps)) {
			flash_error('Please fill required fields and ensure amount ranges are valid.');
			header('Location: ?route=workflows/edit&id=' . $workflowId);
			exit;
		}

		$model = new WorkflowModel();
		$success = $model->updateWorkflow($workflowId, $workflowData, $steps);

		if ($success) {
			RbacService::audit('workflow_update', ['workflow_id' => $workflowId]);
			flash_success('Workflow updated successfully.');
			header('Location: ?route=workflows');
		} else {
			flash_error('Failed to update workflow.');
			header('Location: ?route=workflows/edit&id=' . $workflowId);
		}

		exit;
	}
}
