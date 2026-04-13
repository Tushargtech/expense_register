<?php

class RbacService
{
    private array $auth;
    private ?array $resolvedPermissions = null;
    private static array $rolePermissionCache = [];

    public function __construct(?array $auth = null)
    {
        $this->auth = is_array($auth) ? $auth : (is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : []);
    }

    public function isAuthenticated(): bool
    {
        return !empty($this->auth['is_logged_in']);
    }

    public function role(): string
    {
        $role = strtolower(trim((string) ($this->auth['role'] ?? '')));

        if ($role === 'emp') {
            return 'employee';
        }

        if ($role === 'depthead' || $role === 'department_head') {
            return 'dept_head';
        }

        return $role;
    }

    private function flattenPermissionsArray(array $source, string $prefix = ''): array
    {
        $normalized = [];

        foreach ($source as $key => $value) {
            if (is_int($key)) {
                if (is_string($value) && trim($value) !== '') {
                    $normalized[strtolower(trim($value))] = true;
                }
                continue;
            }

            $normalizedKey = strtolower(trim((string) $key));
            if ($normalizedKey === '') {
                continue;
            }

            $fullKey = $prefix === '' ? $normalizedKey : $prefix . '.' . $normalizedKey;

            if (is_array($value)) {
                $normalized += $this->flattenPermissionsArray($value, $fullKey);
                continue;
            }

            if (is_bool($value)) {
                $normalized[$fullKey] = $value;
                continue;
            }

            if (is_numeric($value)) {
                $normalized[$fullKey] = ((int) $value) === 1;
                continue;
            }

            if (is_string($value)) {
                $text = strtolower(trim($value));
                $normalized[$fullKey] = in_array($text, ['1', 'true', 'yes', 'allow', 'allowed'], true);
            }
        }

        return $normalized;
    }

    private function decodePermissions(mixed $rawPermissions): array
    {
        if (is_array($rawPermissions)) {
            return $this->flattenPermissionsArray($rawPermissions);
        }

        if (!is_string($rawPermissions)) {
            return [];
        }

        $rawText = trim($rawPermissions);
        if ($rawText === '') {
            return [];
        }

        $decoded = json_decode($rawText, true);
        if (is_array($decoded)) {
            return $this->flattenPermissionsArray($decoded);
        }

        $normalized = [];
        $tokens = array_filter(array_map('trim', explode(',', $rawText)), static fn($item) => $item !== '');
        foreach ($tokens as $token) {
            $normalized[strtolower($token)] = true;
        }

        return $normalized;
    }

    private function loadRolePermissions(): array
    {
        if ($this->resolvedPermissions !== null) {
            return $this->resolvedPermissions;
        }

        $sessionPermissions = $this->auth['role_permissions'] ?? null;
        $decodedSessionPermissions = $this->decodePermissions($sessionPermissions);
        if ($decodedSessionPermissions !== []) {
            $this->resolvedPermissions = $decodedSessionPermissions;
            return $this->resolvedPermissions;
        }

        $roleSlug = $this->role();
        if ($roleSlug === '') {
            $this->resolvedPermissions = [];
            return $this->resolvedPermissions;
        }

        if (array_key_exists($roleSlug, self::$rolePermissionCache)) {
            $this->resolvedPermissions = self::$rolePermissionCache[$roleSlug];
            return $this->resolvedPermissions;
        }

        $permissions = [];
        try {
            if (function_exists('getDB')) {
                $db = getDB();
                $stmt = $db->prepare('SELECT role_permissions FROM roles WHERE role_slug = :role_slug LIMIT 1');
                $stmt->execute([':role_slug' => $roleSlug]);
                $raw = $stmt->fetchColumn();
                $permissions = $this->decodePermissions($raw);
            }
        } catch (Throwable $error) {
            $permissions = [];
        }

        self::$rolePermissionCache[$roleSlug] = $permissions;
        $this->resolvedPermissions = $permissions;

        return $this->resolvedPermissions;
    }

    private function hasPermission(array $keys): bool
    {
        $permissions = $this->loadRolePermissions();

        foreach ($keys as $key) {
            $normalizedKey = strtolower(trim($key));
            if ($normalizedKey === '') {
                continue;
            }

            if (($permissions[$normalizedKey] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    public function userId(): int
    {
        return (int) ($this->auth['user_id'] ?? 0);
    }

    public function departmentId(): int
    {
        return (int) ($this->auth['department_id'] ?? 0);
    }

    public function departmentName(): string
    {
        return strtolower(trim((string) ($this->auth['department_name'] ?? '')));
    }

    public function isHrScopedRole(): bool
    {
        return $this->hasPermission(['users.hr_scope', 'users.scope.hr']);
    }

    public function canManageUsersAndDepartments(): bool
    {
        return $this->hasPermission(['users.manage', 'departments.manage', 'can_manage_users_departments', 'canmanageusersanddepartments']);
    }

    public function canViewUsers(): bool
    {
        return $this->hasPermission(['users.view', 'users.list', 'can_view_users', 'canviewusers']);
    }

    public function canManageUsers(): bool
    {
        return $this->hasPermission(['users.manage', 'users.create', 'users.edit', 'can_manage_users', 'canmanageusers']);
    }

    public function canViewDepartments(): bool
    {
        return $this->hasPermission(['departments.view', 'departments.list', 'can_view_departments', 'canviewdepartments']);
    }

    public function canManageDepartments(): bool
    {
        return $this->hasPermission(['departments.manage', 'departments.create', 'departments.edit', 'can_manage_departments', 'canmanagedepartments']);
    }

    public function isDepartmentScopedUserViewer(): bool
    {
        return $this->canViewUsers() && !$this->canViewAllUsers();
    }

    public function canViewAllUsers(): bool
    {
        return $this->hasPermission(['users.view_all', 'users.scope.all', 'can_view_all_users', 'canviewallusers']);
    }

    public function canViewBudgetCategories(): bool
    {
        return $this->hasPermission(['budget_categories.view', 'budget.categories.view', 'can_view_budget_categories', 'canviewbudgetcategories']);
    }

    public function canManageBudgetCategories(): bool
    {
        return $this->hasPermission(['budget_categories.manage', 'budget.categories.manage', 'can_manage_budget_categories', 'canmanagebudgetcategories']);
    }

    public function canManageFinancialSetup(): bool
    {
        return $this->canManageBudgetCategories();
    }

    public function canManageWorkflows(): bool
    {
        return $this->hasPermission(['workflows.manage', 'can_manage_workflows', 'canmanageworkflows']);
    }

    public function canCreateWorkflow(): bool
    {
        return $this->hasPermission(['workflows.create', 'can_create_workflow', 'cancreateworkflow']);
    }

    public function canEditWorkflow(): bool
    {
        return $this->hasPermission(['workflows.edit', 'can_edit_workflow', 'caneditworkflow']);
    }

    public function canViewWorkflow(): bool
    {
        return $this->hasPermission(['workflows.view', 'workflow.view', 'can_view_workflow', 'canviewworkflow']);
    }

    public function canViewWorkflowList(): bool
    {
        return $this->hasPermission(['workflows.list', 'workflow.list', 'can_view_workflow_list', 'canviewworkflowlist']);
    }

    public function canAccessBudgetMonitor(): bool
    {
        return $this->hasPermission(['budget_monitor.view', 'budget.monitor.view', 'can_access_budget_monitor', 'canaccessbudgetmonitor']);
    }

    public function canViewOrganizationBudgetUtilization(): bool
    {
        return $this->hasPermission(['budget_monitor.view_all', 'budget.monitor.scope.all', 'can_view_organization_budget_utilization', 'canvieworganizationbudgetutilization']);
    }

    public function canReviewExpenseRequests(): bool
    {
        return $this->hasPermission(['expenses.review', 'expense.review', 'can_review_expense_requests', 'canreviewexpenserequests']);
    }

    public function canReviewAllExpenseRequests(): bool
    {
        return $this->hasPermission(['expenses.review_all', 'expense.review_all', 'expenses.scope.all']);
    }

    public function canAccessFinancialRequests(): bool
    {
        return $this->hasPermission(['expenses.view', 'expense.view', 'can_access_financial_requests', 'canaccessfinancialrequests']);
    }

    public function canAccessRequest(int $ownerUserId, int $requestDepartmentId): bool
    {
        $currentUserId = $this->userId();
        if ($currentUserId > 0 && $currentUserId === $ownerUserId) {
            return true;
        }

        if (!$this->canReviewExpenseRequests()) {
            return false;
        }

        if ($this->canReviewAllExpenseRequests()) {
            return true;
        }

        return $this->departmentId() > 0 && $requestDepartmentId > 0 && $this->departmentId() === $requestDepartmentId;
    }

    public static function audit(string $action, array $context = []): void
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $requestId = (int) ($_GET['id'] ?? $_POST['id'] ?? $_GET['request_id'] ?? $_POST['request_id'] ?? 0);
        $route = trim((string) ($_GET['route'] ?? ''));
        $method = strtoupper(trim((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
        $ipAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        $record = [
            'event' => 'audit',
            'timestamp' => date('Y-m-d H:i:s'),
            'actor_user_id' => (int) ($auth['user_id'] ?? 0),
            'actor_role' => strtolower(trim((string) ($auth['role'] ?? ''))),
            'actor_department_id' => (int) ($auth['department_id'] ?? 0),
            'route' => $route,
            'method' => $method,
            'ip' => $ipAddress,
            'request_id' => $requestId,
            'action' => $action,
            'context' => $context,
        ];

        error_log(json_encode($record));
    }
}
