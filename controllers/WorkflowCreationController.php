<?php

class WorkflowCreationController
{
	private function ensureAuthenticated(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			$_SESSION['auth_error'] = 'Please login to continue.';
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

			$steps[] = [
				'step_order' => $stepOrder > 0 ? $stepOrder : ($index + 1),
				'step_name' => $stepName,
				'step_approver_type' => trim((string) ($source['step_approver_type'][$index] ?? 'role')),
				'step_approver_role' => trim((string) ($source['step_approver_role'][$index] ?? '')),
				'step_approver_user_id' => $rawApproverUserId === '' ? null : (int) $rawApproverUserId,
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

	public function create(): void
	{
		$this->ensureAuthenticated();

		$model = new WorkflowCreationModel();
		$roles = $model->getRoles();
		$users = $model->getActiveUsers();

		$pageTitle = 'Create Workflow - Expense Register';
		$pageStyles = ['assets/css/app.css'];
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
		$this->ensureAuthenticated();

		$workflowId = (int) ($_GET['id'] ?? 0);
		if ($workflowId <= 0) {
			header('Location: ?route=workflows&error=' . urlencode('Invalid workflow id'));
			exit;
		}

		$model = new WorkflowCreationModel();
		$workflow = $model->getWorkflowById($workflowId);
		if ($workflow === null) {
			header('Location: ?route=workflows&error=' . urlencode('Workflow not found'));
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
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'workflow-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = true;
		$formTitle = 'Edit Workflow';
		$formAction = '?route=workflows/edit&id=' . $workflowId;
		$submitLabel = 'Update Workflow';

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

	public function store(): void
	{
		$this->ensureAuthenticated();

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=workflows/create');
			exit;
		}

		$workflowData = $this->normalizeWorkflowPayload($_POST);
		$steps = $this->normalizeStepsPayload($_POST);

		if (!$this->isValidWorkflowPayload($workflowData, $steps)) {
			header('Location: ?route=workflows/create&error=' . urlencode('Please fill required fields and ensure amount ranges are valid.'));
			exit;
		}

		$model = new WorkflowCreationModel();
		$success = $model->createWorkflow($workflowData, $steps);

		if ($success) {
			header('Location: ?route=workflows&success=' . urlencode('Workflow created successfully.'));
		} else {
			header('Location: ?route=workflows/create&error=' . urlencode('Failed to create workflow.'));
		}

		exit;
	}

	public function update(): void
	{
		$this->ensureAuthenticated();

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=workflows');
			exit;
		}

		$workflowId = (int) ($_GET['id'] ?? 0);
		if ($workflowId <= 0) {
			header('Location: ?route=workflows&error=' . urlencode('Invalid workflow id'));
			exit;
		}

		$workflowData = $this->normalizeWorkflowPayload($_POST);
		$steps = $this->normalizeStepsPayload($_POST);

		if (!$this->isValidWorkflowPayload($workflowData, $steps)) {
			header('Location: ?route=workflows/edit&id=' . $workflowId . '&error=' . urlencode('Please fill required fields and ensure amount ranges are valid.'));
			exit;
		}

		$model = new WorkflowCreationModel();
		$success = $model->updateWorkflow($workflowId, $workflowData, $steps);

		if ($success) {
			header('Location: ?route=workflows&success=' . urlencode('Workflow updated successfully.'));
		} else {
			header('Location: ?route=workflows/edit&id=' . $workflowId . '&error=' . urlencode('Failed to update workflow.'));
		}

		exit;
	}
}
