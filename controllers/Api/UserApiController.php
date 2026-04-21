<?php

class UserApiController extends ApiBaseController
{
    private function ensureListAccess(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canViewUsers(), 'Forbidden');
    }

    private function ensureCrudAccess(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canManageUsers(), 'Forbidden');
    }

    private function normalizePayload(array $source): array
    {
        return [
            'name' => trim((string) ($source['name'] ?? '')),
            'email' => trim((string) ($source['email'] ?? '')),
            'role' => $this->normalizeUserRole((string) ($source['role'] ?? 'employee')),
            'department_id' => (int) ($source['department_id'] ?? 0),
            'manager_id' => (int) ($source['manager_id'] ?? 0),
            'user_is_active' => (int) ($source['user_is_active'] ?? 1),
        ];
    }

    private function normalizeUserRole(string $role): string
    {
        $normalizedRole = strtolower(trim($role));

        if ($normalizedRole === 'emp') {
            return 'employee';
        }

        if (in_array($normalizedRole, ['dept_head', 'depthead', 'department_head', 'manager'], true)) {
            return 'employee';
        }

        if (in_array($normalizedRole, ['hr_manager', 'hr_department_head', 'hr_dept_head'], true)) {
            return 'hr';
        }

        return in_array($normalizedRole, ['admin', 'finance', 'hr', 'employee'], true) ? $normalizedRole : 'employee';
    }

    private function validatePayload(array $userData): array
    {
        $errors = [];
        $lookupModel = new LookupModel();
        $allowedRoles = $lookupModel->getRoleSlugs();
        $normalizedRole = $this->normalizeUserRole((string) ($userData['role'] ?? ''));

        if ($userData['name'] === '') {
            $errors['name'] = 'Name is required.';
        }
        if (filter_var($userData['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Valid email is required.';
        }
        if (!in_array($normalizedRole, $allowedRoles, true)) {
            $errors['role'] = 'Invalid role.';
        }
        if ($userData['department_id'] <= 0) {
            $errors['department_id'] = 'Department is required.';
        }
        if ($userData['manager_id'] <= 0) {
            $errors['manager_id'] = 'Manager is required.';
        }
        if (!in_array($userData['user_is_active'], [0, 1], true)) {
            $errors['user_is_active'] = 'Invalid active flag.';
        }

        return $errors;
    }

    private function visibleFilters(): array
    {
        $rbac = $this->rbac();
        $filters = [
            'search' => $this->request->queryString('search'),
            'role' => $this->request->queryString('role'),
            'department' => $this->request->queryString('department'),
            'status' => $this->request->queryString('status'),
            'department_id_scope' => 0,
        ];

        if ($rbac->isDepartmentScopedUserViewer()) {
            $departmentIdScope = $rbac->departmentId();
            if ($departmentIdScope <= 0) {
                $this->jsonError('Department scope is missing.', 403);
            }

            $filters['department_id_scope'] = $departmentIdScope;
            $filters['department'] = '';
        }

        return $filters;
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
        $userModel = new UserModel();
        $filters = $this->visibleFilters();
        $pageInfo = $this->pagination();
        $totalUsers = $userModel->countAllUsers($filters);
        $users = $userModel->getAllUsers($filters, $pageInfo['limit'], $pageInfo['offset']);

        $this->jsonSuccess($users, [
            'pagination' => [
                'page' => $pageInfo['page'],
                'limit' => $pageInfo['limit'],
                'total' => $totalUsers,
                'pages' => max(1, (int) ceil($totalUsers / $pageInfo['limit'])),
            ],
        ]);
    }

    public function show(int $userId): void
    {
        $this->ensureListAccess();
        $userModel = new UserModel();
        $user = $userModel->getUserById($userId);

        if ($user === null) {
            $this->jsonError('User not found.', 404);
        }

        $this->jsonSuccess($user);
    }

    public function store(): void
    {
        $this->ensureCrudAccess();
        $userData = $this->normalizePayload($this->input());
        $errors = $this->validatePayload($userData);

        if (!empty($errors)) {
            $this->jsonError('Validation failed.', 422, $errors);
        }

        $userModel = new UserModel();
        $result = $userModel->createUser($userData);

        if (!($result['success'] ?? false)) {
            $this->jsonError('Failed to create employee.', 500);
        }

        $userId = (int) ($result['user_id'] ?? 0);
        $tempPassword = (string) ($result['temporary_password'] ?? '');
        $email = (string) ($result['email'] ?? '');
        $name = (string) ($result['name'] ?? '');

        // Send welcome email with temporary password
        if ($userId > 0) {
            // Mark user for forced password change on first login
            $userModel->setForcePasswordChange($userId);

            if ($tempPassword) {
                // Generate login link
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
                $basePath = '/expense_register';
                $loginLink = "{$scheme}://{$host}{$basePath}/";

                // Send welcome email with login link and temporary password
                $mailService = new MailService();
                $sent = $mailService->sendNewUserEmail($email, $name, $tempPassword, $loginLink, 120);

                if (!$sent) {
                    error_log("Failed to send welcome email to {$email} for user {$userId}");
                }
            }
        }

        RbacService::audit('user_create', ['email' => $userData['email'], 'role' => $userData['role']]);
        $this->jsonSuccess([
            'message' => 'Employee created successfully. Welcome email has been sent.',
            'user_id' => $userId,
        ], [], 201);
    }

    public function update(int $userId): void
    {
        $this->ensureCrudAccess();
        if ($userId <= 0) {
            $this->jsonError('Invalid user id.', 422);
        }

        $userModel = new UserModel();
        $existingUser = $userModel->getUserById($userId);
        if ($existingUser === null) {
            $this->jsonError('User not found.', 404);
        }

        $userData = $this->normalizePayload($this->input());
        $errors = $this->validatePayload($userData);
        if (!empty($errors)) {
            $this->jsonError('Validation failed.', 422, $errors);
        }

        if (!$userModel->updateUser($userId, $userData)) {
            $this->jsonError('Failed to update employee.', 500);
        }

        RbacService::audit('user_update', ['user_id' => $userId, 'role' => $userData['role']]);
        $oldRole = strtolower(trim((string) ($existingUser['user_role'] ?? '')));
        $newRole = strtolower(trim((string) ($userData['role'] ?? '')));
        if ($oldRole !== '' && $newRole !== '' && $oldRole !== $newRole) {
            RbacService::audit('user_role_change', ['user_id' => $userId, 'from' => $oldRole, 'to' => $newRole]);
        }

        $this->jsonSuccess(['message' => 'Employee updated successfully.']);
    }
}