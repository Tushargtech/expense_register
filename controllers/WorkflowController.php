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
		if (!$this->rbac()->canViewWorkflow() && !$this->rbac()->canEditWorkflow()) {
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

	private function resolveApproverUserRoleMap(): array
	{
		$map = [];
		foreach ((new WorkflowModel())->getActiveUsers() as $user) {
			$userId = (int) ($user['user_id'] ?? 0);
			$approverRole = strtolower(trim((string) ($user['approver_role'] ?? '')));
			if ($userId > 0 && $approverRole !== '') {
				$map[$userId] = $approverRole;
			}
		}

		return $map;
	}

	private function normalizeWorkflowPayload(array $source): array
	{
		$rawMin = trim((string) ($source['workflow_amount_min'] ?? ''));
		$rawMax = trim((string) ($source['workflow_amount_max'] ?? ''));
		$workflowDescription = trim((string) ($source['workflow_description'] ?? ''));
		$workflowType = strtolower(trim((string) ($source['workflow_type'] ?? '')));
		$lookupModel = new LookupModel();
		$allowedTypes = array_map(static fn(string $type): string => strtolower($type), $lookupModel->getWorkflowTypes());
		if ($allowedTypes === [] && $workflowType !== '') {
			$allowedTypes = [$workflowType];
		}
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
		$stepIds = isset($source['step_id']) && is_array($source['step_id']) ? $source['step_id'] : [];
		$stepNames = isset($source['step_name']) && is_array($source['step_name']) ? $source['step_name'] : [];
		$roleValues = isset($source['step_approver_role']) && is_array($source['step_approver_role']) ? array_values($source['step_approver_role']) : [];
		$roleValueCursor = 0;
		$allowedApproverTypes = ['role', 'manager', 'department_head'];

		$stepCount = max(count($stepIds), count($stepNames));

		for ($index = 0; $index < $stepCount; $index++) {
			$stepNameValue = $stepNames[$index] ?? '';
			$stepName = trim((string) $stepNameValue);
			$stepId = (int) ($stepIds[$index] ?? 0);
			if ($stepName === '' && $stepId <= 0) {
				continue;
			}

			$stepOrder = (int) ($source['step_order'][$index] ?? ($index + 1));
			$rawTimeoutHours = trim((string) ($source['step_timeout_hours'][$index] ?? ''));

			if (
				$stepName === '' &&
				$rawTimeoutHours === '' &&
				trim((string) ($source['step_approver_role'][$index] ?? '')) === '' &&
				trim((string) ($source['step_approver_type'][$index] ?? '')) === '' &&
				$stepId <= 0
			) {
				continue;
			}

			$approverType = trim((string) ($source['step_approver_type'][$index] ?? 'role'));
			if (!in_array($approverType, $allowedApproverTypes, true)) {
				$approverType = 'role';
			}

			$approverRole = '';
			if ($approverType === 'role') {
				$approverRole = trim((string) ($roleValues[$roleValueCursor] ?? ''));
				$roleValueCursor++;
			} elseif ($approverType === 'manager') {
				$approverRole = 'manager';
			} elseif ($approverType === 'department_head') {
				$approverRole = 'department_head';
			}

			$approverUserId = (int) ($source['step_approver_user_id'][$index] ?? 0);

			$steps[] = [
				'step_id' => $stepId,
				'step_order' => $stepOrder > 0 ? $stepOrder : ($index + 1),
				'step_name' => $stepName,
				'step_approver_type' => $approverType,
				'step_approver_role' => $approverRole,
				'step_approver_user_id' => $approverUserId > 0 ? $approverUserId : 0,
				'step_is_required' => (int) (($source['step_is_required'][$index] ?? '1') === '1' ? 1 : 0),
				'step_timeout_hours' => $rawTimeoutHours === '' ? null : max(1, (int) $rawTimeoutHours),
			];
		}

		return $steps;
	}

	private function preserveInactiveStepValues(int $workflowId, array $steps, WorkflowModel $model): array
	{
		$existingSteps = [];
		foreach ($model->getWorkflowStepsByWorkflowId($workflowId) as $step) {
			$stepId = (int) ($step['step_id'] ?? 0);
			if ($stepId > 0) {
				$existingSteps[$stepId] = $this->formatStepForForm($step);
			}
		}

		foreach ($steps as $index => $step) {
			$stepId = (int) ($step['step_id'] ?? 0);
			if ($stepId <= 0 || (int) ($step['step_is_required'] ?? 1) === 1 || !isset($existingSteps[$stepId])) {
				continue;
			}

			$steps[$index] = [
				'step_id' => $stepId,
				'step_order' => (int) ($existingSteps[$stepId]['step_order'] ?? ($index + 1)),
				'step_name' => (string) ($existingSteps[$stepId]['step_name'] ?? ''),
				'step_approver_type' => (string) ($existingSteps[$stepId]['step_approver_type'] ?? 'role'),
				'step_approver_role' => (string) ($existingSteps[$stepId]['step_approver_role'] ?? ''),
				'step_approver_user_id' => (int) ($existingSteps[$stepId]['step_approver_user_id'] ?? 0),
				'step_timeout_hours' => ($existingSteps[$stepId]['step_timeout_hours'] ?? '') === '' ? null : max(1, (int) $existingSteps[$stepId]['step_timeout_hours']),
				'step_is_required' => 0,
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
			'step_approver_user_id' => (int) ($step['step_approver_user_id'] ?? 0),
			'step_timeout_hours' => $step['step_timeout_hours'] !== null && $step['step_timeout_hours'] !== '' ? (string) $step['step_timeout_hours'] : '',
			'step_is_required' => (int) ($step['step_is_required'] ?? 1) === 1,
		];
	}

	private function isValidWorkflowPayload(array $workflowData, array $steps): bool
	{
		if ($workflowData['workflow_name'] === '' || $workflowData['workflow_type'] === '') {
			return false;
		}

		if (trim((string) ($workflowData['workflow_description'] ?? '')) === '') {
			return false;
		}

		if ($workflowData['workflow_amount_min'] === null || $workflowData['workflow_amount_max'] === null) {
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

		$approverUserRoleMap = $this->resolveApproverUserRoleMap();

		foreach ($steps as $step) {
			if ($step['step_name'] === '') {
				return false;
			}

			if (($step['step_timeout_hours'] ?? null) === null || (int) ($step['step_timeout_hours'] ?? 0) <= 0) {
				return false;
			}

			$approverType = strtolower(trim((string) ($step['step_approver_type'] ?? '')));
			$approverRole = strtolower(trim((string) ($step['step_approver_role'] ?? '')));
			$approverUserId = (int) ($step['step_approver_user_id'] ?? 0);

			if (!in_array($approverType, ['role', 'manager', 'department_head'], true)) {
				return false;
			}

			if ($approverType === 'role') {
				if ($approverRole === '') {
					return false;
				}

				if ($approverUserId > 0 && ($approverUserRoleMap[$approverUserId] ?? '') !== $approverRole) {
					return false;
				}
			} else {
				$approverRole = $approverType;

				if ($approverUserId > 0 && ($approverUserRoleMap[$approverUserId] ?? '') !== $approverRole) {
					return false;
				}
			}
		}

		return true;
	}

	private function renderForm(array $viewData): void
	{
		extract($viewData, EXTR_SKIP);

		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle ?? 'Expense Register',
			'pageStyles' => $pageStyles ?? [],
			'activeMenu' => $activeMenu ?? 'dashboard',
		]);
		require ROOT_PATH . '/views/WorkflowManagement/workflow_creation.php';
		renderAppLayoutEnd();
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
		if (!empty($_GET['download'])) {
			$allWorkflows = $workflowModel->getAllWorkflows($filters, max(1, $totalWorkflows), 0);
			$exportRows = [];
			foreach ($allWorkflows as $workflow) {
				$workflowType = strtolower(trim((string) ($workflow['workflow_type'] ?? '')));
				$workflowTypeLabel = match ($workflowType) {
					'expense' => 'Expense',
					'purchase' => 'Purchase',
					default => ucfirst($workflowType),
				};
				$amountMin = $workflow['workflow_amount_min'] ?? null;
				$amountMax = $workflow['workflow_amount_max'] ?? null;
				if ($amountMin === null && $amountMax === null) {
					$amountRange = '-';
				} elseif ($amountMin !== null && $amountMax !== null) {
					$amountRange = number_format((float) $amountMin, 2) . ' - ' . number_format((float) $amountMax, 2);
				} elseif ($amountMin !== null) {
					$amountRange = '>= ' . number_format((float) $amountMin, 2);
				} else {
					$amountRange = '<= ' . number_format((float) $amountMax, 2);
				}

				$exportRows[] = [
					(string) ($workflow['workflow_name'] ?? ''),
					$workflowTypeLabel !== '' ? $workflowTypeLabel : '-',
					$amountRange,
					(string) ($workflow['approval_flow'] ?? '-'),
				];
			}

			$exportService = new SpreadsheetExportService();
			$exportService->streamXlsx(
				'workflows-' . date('YmdHis') . '.xlsx',
				['Workflow Name', 'Workflow Type', 'Amount Range', 'Approval Flow'],
				$exportRows,
				'Workflows'
			);
		}
		$totalPages = max(1, (int) ceil($totalWorkflows / $perPage));

		if ($currentPage > $totalPages) {
			$currentPage = $totalPages;
		}

		$offset = ($currentPage - 1) * $perPage;
		$workflows = $workflowModel->getAllWorkflows($filters, $perPage, $offset);
		$workflowTypes = $workflowModel->getAllWorkflowTypes();

		$pageTitle = 'Workflow List - Expense Register';
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'workflow-list';
		$canCreateWorkflow = $this->rbac()->canCreateWorkflow();
		$canEditWorkflow = $this->rbac()->canEditWorkflow();

		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle,
			'pageStyles' => $pageStyles,
			'activeMenu' => $activeMenu,
		]);
		require ROOT_PATH . '/views/WorkflowManagement/workflow_list.php';
		renderAppLayoutEnd();
	}

	public function create(): void
	{
		$this->ensureWorkflowCreateAccess();

		$model = new WorkflowModel();
		$categoryModel = new BudgetCategoryModel();
		$roles = $model->getRoles();
		$users = $model->getActiveUsers();
		$budgetCategories = $categoryModel->getSelectableCategories();
		$lookupModel = new LookupModel();
		$workflowTypeOptions = $lookupModel->getWorkflowTypes();

		$pageTitle = 'Create Workflow - Expense Register';
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'workflow-list';
		$formError = trim((string) ($_GET['error'] ?? ''));
		$isEdit = false;
		$formTitle = 'Create Workflow';
		$formAction = buildCleanRouteUrl('workflows/create');
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
				'step_timeout_hours' => '',
				'step_is_required' => true,
			],
		];

		$this->renderForm(compact(
			'roles',
			'users',
			'budgetCategories',
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
			'workflowTypeOptions',
			'workflowSteps'
		));
	}

	public function edit(): void
	{
		$this->ensureWorkflowViewAccess();

		$workflowId = (int) ($_GET['id'] ?? 0);
		if ($workflowId <= 0) {
			flash_error('Invalid workflow id');
			header('Location: ' . buildCleanRouteUrl('workflows'));
			exit;
		}

		$model = new WorkflowModel();
		$categoryModel = new BudgetCategoryModel();
		$workflow = $model->getWorkflowById($workflowId);
		if ($workflow === null) {
			flash_error('Workflow not found');
			header('Location: ' . buildCleanRouteUrl('workflows'));
			exit;
		}

		$roles = $model->getRoles();
		$users = $model->getActiveUsers();
		$budgetCategories = $categoryModel->getSelectableCategories();
		$lookupModel = new LookupModel();
		$workflowTypeOptions = $lookupModel->getWorkflowTypes();
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
		$canEditWorkflow = $this->rbac()->canEditWorkflow();
		$formTitle = $canEditWorkflow ? 'Edit Workflow' : 'View Workflow';
		$formAction = buildCleanRouteUrl('workflows/edit', ['id' => $workflowId]);
		$submitLabel = $canEditWorkflow ? 'Update Workflow' : 'Back to Workflow List';

		$this->renderForm(compact(
			'roles',
			'users',
			'budgetCategories',
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
			'workflowTypeOptions',
			'workflow',
			'workflowSteps'
		));
	}

	public function store(): void
	{
		$this->ensureWorkflowCreateAccess();

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ' . buildCleanRouteUrl('workflows/create'));
			exit;
		}

		$workflowData = $this->normalizeWorkflowPayload($_POST);
		$steps = $this->normalizeStepsPayload($_POST);

		if (!$this->isValidWorkflowPayload($workflowData, $steps)) {
			flash_error('Please fill required fields and ensure workflow amount range is valid.');
			header('Location: ' . buildCleanRouteUrl('workflows/create'));
			exit;
		}

		$model = new WorkflowModel();
		$success = $model->createWorkflow($workflowData, $steps);

		if ($success) {
			RbacService::audit('workflow_create', ['workflow_name' => $workflowData['workflow_name']]);
			flash_success('Workflow created successfully.');
			header('Location: ' . buildCleanRouteUrl('workflows'));
		} else {
			flash_error('Failed to create workflow.');
			header('Location: ' . buildCleanRouteUrl('workflows/create'));
		}

		exit;
	}

	public function update(): void
	{
		$this->ensureWorkflowEditAccess();

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ' . buildCleanRouteUrl('workflows'));
			exit;
		}

		$workflowId = (int) ($_GET['id'] ?? 0);
		if ($workflowId <= 0) {
			flash_error('Invalid workflow id');
			header('Location: ' . buildCleanRouteUrl('workflows'));
			exit;
		}

		$model = new WorkflowModel();
		$workflowData = $this->normalizeWorkflowPayload($_POST);
		$steps = $this->preserveInactiveStepValues($workflowId, $this->normalizeStepsPayload($_POST), $model);

		if (!$this->isValidWorkflowPayload($workflowData, $steps)) {
			flash_error('Please fill required fields and ensure workflow amount range is valid.');
			header('Location: ' . buildCleanRouteUrl('workflows/edit', ['id' => $workflowId]));
			exit;
		}

		$success = $model->updateWorkflow($workflowId, $workflowData, $steps);

		if ($success) {
			RbacService::audit('workflow_update', ['workflow_id' => $workflowId]);
			flash_success('Workflow updated successfully.');
			header('Location: ' . buildCleanRouteUrl('workflows'));
		} else {
			flash_error('Failed to update workflow.');
			header('Location: ' . buildCleanRouteUrl('workflows/edit', ['id' => $workflowId]));
		}

		exit;
	}
}
