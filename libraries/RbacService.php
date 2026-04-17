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

        if ($role === 'depthead' || $role === 'dept_head') {
            return 'department_head';
        }

        if ($role === 'hr' || str_starts_with($role, 'hr_')) {
            return 'hr';
        }

        return $role;
    }

    private function rawRole(): string
    {
        return strtolower(trim((string) ($this->auth['role'] ?? '')));
    }

    private function baseRole(): string
    {
        return strtolower(trim((string) ($this->auth['base_role'] ?? $this->auth['role'] ?? '')));
    }

    public function isManager(): bool
    {
        if (in_array($this->baseRole(), ['admin', 'finance'], true)) {
            return false;
        }

        if (!empty($this->auth['is_manager'])) {
            return true;
        }

        return in_array($this->rawRole(), ['manager', 'hr_manager'], true);
    }

    public function isDepartmentHead(): bool
    {
        if (in_array($this->baseRole(), ['admin', 'finance'], true)) {
            return false;
        }

        if (!empty($this->auth['is_department_head'])) {
            return true;
        }

        return in_array($this->rawRole(), ['department_head', 'dept_head', 'depthead', 'hr_department_head', 'hr_dept_head'], true);
    }

    private function isHrManagerOrDepartmentHead(): bool
    {
        return $this->isManager() || $this->isDepartmentHead();
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

        $rawRole = strtolower(trim((string) ($this->auth['role'] ?? '')));
        $roleSlug = $this->role();
        $sessionPermissions = $this->auth['role_permissions'] ?? null;
        $decodedSessionPermissions = $this->decodePermissions($sessionPermissions);
        if ($roleSlug === '') {
            $this->resolvedPermissions = $decodedSessionPermissions;
            return $this->resolvedPermissions;
        }

        if (array_key_exists($roleSlug, self::$rolePermissionCache)) {
            $this->resolvedPermissions = self::$rolePermissionCache[$roleSlug];
            return $this->resolvedPermissions;
        }

        $roleCandidates = array_values(array_unique(array_filter([$roleSlug, $rawRole], static fn($value) => $value !== '')));

        try {
            if (function_exists('getDB') && $roleCandidates !== []) {
                $db = getDB();
                foreach ($roleCandidates as $candidateRoleSlug) {
                    if (array_key_exists($candidateRoleSlug, self::$rolePermissionCache)) {
                        $permissions = self::$rolePermissionCache[$candidateRoleSlug];
                        self::$rolePermissionCache[$roleSlug] = $permissions;
                        $this->resolvedPermissions = $permissions;
                        return $this->resolvedPermissions;
                    }

                    $stmt = $db->prepare('SELECT role_permissions FROM roles WHERE role_slug = :role_slug LIMIT 1');
                    $stmt->execute([':role_slug' => $candidateRoleSlug]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!is_array($row)) {
                        continue;
                    }

                    $permissions = $this->decodePermissions($row['role_permissions'] ?? null);
                    self::$rolePermissionCache[$candidateRoleSlug] = $permissions;
                    self::$rolePermissionCache[$roleSlug] = $permissions;
                    $this->resolvedPermissions = $permissions;
                    return $this->resolvedPermissions;
                }
            }
        } catch (Throwable $error) {
            // Fall back to session-cached permissions if DB lookup fails.
        }

        if ($decodedSessionPermissions !== []) {
            self::$rolePermissionCache[$roleSlug] = $decodedSessionPermissions;
            $this->resolvedPermissions = $decodedSessionPermissions;
            return $this->resolvedPermissions;
        }

        self::$rolePermissionCache[$roleSlug] = [];
        $this->resolvedPermissions = [];

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
        return $this->role() === 'hr';
    }

    public function canManageUsersAndDepartments(): bool
    {
        return $this->canManageUsers() && $this->canManageDepartments();
    }

    public function canViewUsers(): bool
    {
        return $this->hasPermission(['users.view']);
    }

    public function canManageUsers(): bool
    {
        return $this->hasPermission(['users.manage']);
    }

    public function canViewDepartments(): bool
    {
        return $this->hasPermission(['departments.view']);
    }

    public function canManageDepartments(): bool
    {
        if ($this->hasPermission(['departments.manage'])) {
            return true;
        }

        return $this->role() === 'admin';
    }

    public function isDepartmentScopedUserViewer(): bool
    {
        return $this->canViewUsers() && !$this->canViewAllUsers();
    }

    public function canViewAllUsers(): bool
    {
        return $this->hasPermission(['users.view_all']);
    }

    public function canViewBudgetCategories(): bool
    {
        if ($this->hasPermission(['budget_categories.view'])) {
            return true;
        }

        return $this->isHrManagerOrDepartmentHead();
    }

    public function canManageBudgetCategories(): bool
    {
        // Budget category create/update is finance-only.
        if ($this->role() !== 'finance') {
            return false;
        }

        // Allow finance even if session-cached permissions are stale.
        return $this->hasPermission(['budget_categories.manage']) || $this->role() === 'finance';
    }

    public function canManageFinancialSetup(): bool
    {
        return $this->canManageBudgetRecords();
    }

    public function canManageBudgetRecords(): bool
    {
        $role = $this->role();
        $departmentName = $this->departmentName();

        // Finance role (finance employee) can always manage budget records
        if ($role === 'finance') {
            return true;
        }

        // Finance department employees, managers, and department heads can upload budgets
        if ($departmentName === 'finance') {
            return in_array($role, ['employee'], true) || $this->isManager() || $this->isDepartmentHead();
        }

        return false;
    }

    public function canManageWorkflows(): bool
    {
        return $this->hasPermission(['workflows.manage']);
    }

    public function canCreateWorkflow(): bool
    {
        return $this->hasPermission(['workflows.create']);
    }

    public function canEditWorkflow(): bool
    {
        if ($this->hasPermission(['workflows.edit', 'workflows.manage'])) {
            return true;
        }

        return in_array($this->role(), ['admin', 'finance'], true) || $this->isDepartmentHead();
    }

    public function canViewWorkflow(): bool
    {
        if ($this->hasPermission(['workflows.view'])) {
            return true;
        }

        return $this->isDepartmentHead();
    }

    public function canViewWorkflowList(): bool
    {
        if ($this->hasPermission(['workflows.list'])) {
            return true;
        }

        return $this->isDepartmentHead();
    }

    public function canAccessBudgetMonitor(): bool
    {
        $role = $this->role();
        $departmentName = $this->departmentName();

        // Finance role can always access budget monitor
        if ($role === 'finance') {
            return true;
        }

        // Finance department employees, managers, and department heads can access budget monitor
        if ($departmentName === 'finance') {
            return in_array($role, ['employee'], true) || $this->isManager() || $this->isDepartmentHead();
        }

        // Department heads from other departments can access budget monitor for their department only
        if ($this->isDepartmentHead()) {
            return true;
        }

        return false;
    }

    // Only Finance department users can view budgets for all departments
    public function canAccessBudgetMonitorForAllDepartments(): bool
    {
        $role = $this->role();
        $departmentName = $this->departmentName();

        // Finance role can view all departments
        if ($role === 'finance') {
            return true;
        }

        // Finance department employees, managers, and department heads can view all departments
        if ($departmentName === 'finance') {
            return in_array($role, ['employee'], true) || $this->isManager() || $this->isDepartmentHead();
        }

        return false;
    }

    public function canViewOrganizationBudgetUtilization(): bool
    {
        return $this->hasPermission(['budget_monitor.view_all']);
    }

    public function canReviewExpenseRequests(): bool
    {
        return $this->hasPermission(['expenses.review']) || $this->isManager() || $this->isDepartmentHead();
    }

    public function canReviewAllExpenseRequests(): bool
    {
        return $this->hasPermission(['expenses.review_all']);
    }

    public function canAccessFinancialRequests(): bool
    {
        return $this->hasPermission(['expenses.view']);
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
