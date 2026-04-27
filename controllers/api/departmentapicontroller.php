<?php

class DepartmentApiController extends ApiBaseController
{
    private function ensureListAccess(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canViewDepartments(), 'Forbidden');
    }

    private function ensureCrudAccess(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canManageDepartments(), 'Forbidden');
    }

    private function normalizePayload(array $source): array
    {
        return [
            'department_name' => trim((string) ($source['department_name'] ?? '')),
            'department_code' => trim((string) ($source['department_code'] ?? '')),
            'department_head_user_id' => (int) ($source['department_head_user_id'] ?? 0),
        ];
    }

    private function validatePayload(array $departmentData, int $excludeDepartmentId = 0): array
    {
        $errors = [];
        if ($departmentData['department_name'] === '') {
            $errors['department_name'] = 'Department name is required.';
        }
        if ($departmentData['department_code'] === '') {
            $errors['department_code'] = 'Department code is required.';
        }
        if ($departmentData['department_head_user_id'] <= 0) {
            $errors['department_head_user_id'] = 'Department head is required.';
        } else {
            $deptModel = new DepartmentModel();
            $conflict = $deptModel->getDepartmentHeadConflict((int) $departmentData['department_head_user_id'], $excludeDepartmentId);
            if ($conflict !== null) {
                $conflictDepartmentName = trim((string) ($conflict['department_name'] ?? ''));
                $errors['department_head_user_id'] = $conflictDepartmentName !== ''
                    ? 'Department head is already assigned to ' . $conflictDepartmentName . '.'
                    : 'Department head is already assigned to another department.';
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
        $deptModel = new DepartmentModel();
        $departments = $deptModel->getAllDepartments();
        $searchValue = $this->request->queryString('search');

        if ($searchValue !== '') {
            $searchLower = strtolower($searchValue);
            $departments = array_values(array_filter($departments, static function (array $dept) use ($searchLower): bool {
                $code = strtolower((string) ($dept['department_code'] ?? ''));
                $name = strtolower((string) ($dept['department_name'] ?? ''));
                return str_contains($code, $searchLower) || str_contains($name, $searchLower);
            }));
        }

        $pageInfo = $this->pagination();
        $total = count($departments);
        $departments = array_slice($departments, $pageInfo['offset'], $pageInfo['limit']);

        $this->jsonSuccess($departments, [
            'pagination' => [
                'page' => $pageInfo['page'],
                'limit' => $pageInfo['limit'],
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $pageInfo['limit'])),
            ],
        ]);
    }

    public function show(int $departmentId): void
    {
        $this->ensureListAccess();
        $deptModel = new DepartmentModel();
        $department = $deptModel->getDepartmentById($departmentId);

        if ($department === null) {
            $this->jsonError('Department not found.', 404);
        }

        $this->jsonSuccess($department);
    }

    public function store(): void
    {
        $this->ensureCrudAccess();
        $departmentData = $this->normalizePayload($this->input());
        $errors = $this->validatePayload($departmentData);

        if (!empty($errors)) {
            $this->jsonError('Validation failed.', 422, $errors);
        }

        $deptModel = new DepartmentModel();
        if (!$deptModel->createDepartment($departmentData)) {
            $this->jsonError('Failed to create department.', 500);
        }

        $createdId = 0;
        try {
            $stmt = getDB()->prepare('SELECT id FROM departments WHERE department_code = :code ORDER BY id DESC LIMIT 1');
            $stmt->execute([':code' => $departmentData['department_code']]);
            $createdId = (int) ($stmt->fetchColumn() ?: 0);
        } catch (Throwable $error) {
            $createdId = 0;
        }

        RbacService::audit('department_create', ['department_code' => $departmentData['department_code']]);
        $this->jsonSuccess([
            'message' => 'Department created successfully.',
            'department_id' => $createdId,
        ], [], 201);
    }

    public function update(int $departmentId): void
    {
        $this->ensureCrudAccess();
        if ($departmentId <= 0) {
            $this->jsonError('Invalid department id.', 422);
        }

        $departmentData = $this->normalizePayload($this->input());
        $errors = $this->validatePayload($departmentData, $departmentId);
        if (!empty($errors)) {
            $this->jsonError('Validation failed.', 422, $errors);
        }

        $deptModel = new DepartmentModel();
        if (!$deptModel->updateDepartment($departmentId, $departmentData)) {
            $this->jsonError('Failed to update department.', 500);
        }

        RbacService::audit('department_update', ['department_id' => $departmentId]);
        $this->jsonSuccess(['message' => 'Department updated successfully.']);
    }
}