<?php

class WorkflowApiController extends ApiBaseController
{
    private function ensureListAccess(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canViewWorkflowList(), 'Forbidden');
    }

    private function ensureViewAccess(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canViewWorkflow(), 'Forbidden');
    }

    private function ensureCreateAccess(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canCreateWorkflow(), 'Forbidden');
    }

    private function ensureEditAccess(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canEditWorkflow(), 'Forbidden');
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
        $normalizedType = in_array($workflowType, $allowedTypes, true) ? ucfirst($workflowType) : '';

        return [
            'workflow_name' => trim((string) ($source['workflow_name'] ?? '')),
            'workflow_description' => $workflowDescription !== '' ? $workflowDescription : null,
            'budget_category_id' => (int) ($source['budget_category_id'] ?? 0),
            'workflow_type' => $normalizedType,
            'workflow_is_active' => (int) ($source['workflow_is_active'] ?? 1) === 1 ? 1 : 0,
            'workflow_is_default' => (int) ($source['workflow_is_default'] ?? 0) === 1 ? 1 : 0,
            'workflow_amount_min' => $rawMin === '' ? null : (float) $rawMin,
            'workflow_amount_max' => $rawMax === '' ? null : (float) $rawMax,
            'workflow_created_by' => (int) ($this->authenticatedUser()['user_id'] ?? 0),
        ];
    }

    private function normalizeStepsPayload(array $source): array
    {
        $steps = [];
        $stepNames = isset($source['step_name']) && is_array($source['step_name']) ? $source['step_name'] : [];
        $roleValues = isset($source['step_approver_role']) && is_array($source['step_approver_role']) ? array_values($source['step_approver_role']) : [];
        $roleValueCursor = 0;
        $allowedApproverTypes = ['role', 'manager', 'department_head'];

        foreach ($stepNames as $index => $stepNameValue) {
            $stepName = trim((string) $stepNameValue);
            if ($stepName === '') {
                continue;
            }

            $stepOrder = (int) ($source['step_order'][$index] ?? ($index + 1));
            $rawTimeoutHours = trim((string) ($source['step_timeout_hours'][$index] ?? ''));

            if (
                $stepName === '' &&
                $rawTimeoutHours === '' &&
                trim((string) ($source['step_approver_role'][$index] ?? '')) === '' &&
                trim((string) ($source['step_approver_type'][$index] ?? '')) === ''
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

            $steps[] = [
                'step_order' => $stepOrder > 0 ? $stepOrder : ($index + 1),
                'step_name' => $stepName,
                'step_approver_type' => $approverType,
                'step_approver_role' => $approverRole,
                'step_is_required' => (int) (($source['step_is_required'][$index] ?? '1') === '1' ? 1 : 0),
                'step_timeout_hours' => $rawTimeoutHours === '' ? null : max(1, (int) $rawTimeoutHours),
            ];
        }

        return $steps;
    }

    private function validateWorkflowPayload(array $workflowData, array $steps): array
    {
        $errors = [];

        if ($workflowData['workflow_name'] === '') {
            $errors['workflow_name'] = 'Workflow name is required.';
        }
        if ((int) ($workflowData['budget_category_id'] ?? 0) <= 0) {
            $errors['budget_category_id'] = 'Workflow category is required.';
        } else {
            $category = (new BudgetCategoryModel())->getCategoryById((int) $workflowData['budget_category_id']);
            if ($category === null || (int) ($category['budget_category_is_active'] ?? 0) !== 1) {
                $errors['budget_category_id'] = 'Selected workflow category is invalid.';
            }
        }
        if ($workflowData['workflow_type'] === '') {
            $errors['workflow_type'] = 'Workflow type is required.';
        }
        if (
            $workflowData['workflow_amount_min'] !== null &&
            $workflowData['workflow_amount_max'] !== null &&
            $workflowData['workflow_amount_min'] > $workflowData['workflow_amount_max']
        ) {
            $errors['workflow_amount_range'] = 'Workflow amount range is invalid.';
        }
        if (count($steps) === 0) {
            $errors['steps'] = 'At least one workflow step is required.';
        }

        foreach ($steps as $index => $step) {
            if ($step['step_name'] === '') {
                $errors['steps.' . $index . '.step_name'] = 'Step name is required.';
            }
            if ($step['step_approver_type'] === 'role' && trim((string) ($step['step_approver_role'] ?? '')) === '') {
                $errors['steps.' . $index . '.step_approver_role'] = 'Step approver role is required.';
            }
            if (!in_array((string) ($step['step_approver_type'] ?? ''), ['role', 'manager', 'department_head'], true)) {
                $errors['steps.' . $index . '.step_approver_type'] = 'Invalid approver type.';
            }
        }

        return $errors;
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

    public function index(): void
    {
        $this->ensureListAccess();
        $workflowModel = new WorkflowModel();
        $filters = [
            'search' => $this->request->queryString('search'),
            'status' => $this->request->queryString('status'),
            'workflow_type' => $this->request->queryString('workflow_type'),
            'budget_category_id' => (int) ($this->request->queryString('budget_category_id') ?? 0),
        ];

        $pageInfo = $this->pagination();
        $total = $workflowModel->countAllWorkflows($filters);
        $workflows = $workflowModel->getAllWorkflows($filters, $pageInfo['limit'], $pageInfo['offset']);

        $this->jsonSuccess($workflows, [
            'pagination' => [
                'page' => $pageInfo['page'],
                'limit' => $pageInfo['limit'],
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $pageInfo['limit'])),
            ],
            'workflow_types' => $workflowModel->getAllWorkflowTypes(),
            'budget_categories' => (new BudgetCategoryModel())->getSelectableCategories(),
        ]);
    }

    public function show(int $workflowId): void
    {
        $this->ensureViewAccess();
        $workflowModel = new WorkflowModel();
        $workflow = $workflowModel->getWorkflowById($workflowId);

        if ($workflow === null) {
            $this->jsonError('Workflow not found.', 404);
        }

        $steps = $workflowModel->getWorkflowStepsByWorkflowId($workflowId);
        $this->jsonSuccess([
            'workflow' => $workflow,
            'steps' => array_map([$this, 'formatStepForApi'], $steps),
            'roles' => $workflowModel->getRoles(),
            'workflow_types' => (new LookupModel())->getWorkflowTypes(),
            'budget_categories' => (new BudgetCategoryModel())->getSelectableCategories(),
        ]);
    }

    public function store(): void
    {
        $this->ensureCreateAccess();
        $workflowData = $this->normalizeWorkflowPayload($this->input());
        $steps = $this->normalizeStepsPayload($this->input());
        $errors = $this->validateWorkflowPayload($workflowData, $steps);

        if (!empty($errors)) {
            $this->jsonError('Validation failed.', 422, $errors);
        }

        $model = new WorkflowModel();
        if (!$model->createWorkflow($workflowData, $steps)) {
            $this->jsonError('Failed to create workflow.', 500);
        }

        $createdId = 0;
        try {
            $stmt = getDB()->prepare('SELECT workflow_id FROM workflows WHERE workflow_name = :name AND workflow_created_by = :created_by ORDER BY workflow_id DESC LIMIT 1');
            $stmt->execute([
                ':name' => $workflowData['workflow_name'],
                ':created_by' => (int) ($workflowData['workflow_created_by'] ?? 0),
            ]);
            $createdId = (int) ($stmt->fetchColumn() ?: 0);
        } catch (Throwable $error) {
            $createdId = 0;
        }

        RbacService::audit('workflow_create', ['workflow_name' => $workflowData['workflow_name']]);
        $this->jsonSuccess([
            'message' => 'Workflow created successfully.',
            'workflow_id' => $createdId,
        ], [], 201);
    }

    public function update(int $workflowId): void
    {
        $this->ensureEditAccess();
        if ($workflowId <= 0) {
            $this->jsonError('Invalid workflow id.', 422);
        }

        $workflowData = $this->normalizeWorkflowPayload($this->input());
        $steps = $this->normalizeStepsPayload($this->input());
        $errors = $this->validateWorkflowPayload($workflowData, $steps);

        if (!empty($errors)) {
            $this->jsonError('Validation failed.', 422, $errors);
        }

        $model = new WorkflowModel();
        if (!$model->updateWorkflow($workflowId, $workflowData, $steps)) {
            $this->jsonError('Failed to update workflow.', 500);
        }

        RbacService::audit('workflow_update', ['workflow_id' => $workflowId]);
        $this->jsonSuccess(['message' => 'Workflow updated successfully.']);
    }
}