<?php

class RbacService
{
    private array $auth;

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
        $role = $this->role();
        return $role === 'hr' || ($this->departmentName() === 'hr' && in_array($role, ['manager', 'dept_head', 'department_head'], true));
    }

    public function canManageUsersAndDepartments(): bool
    {
        return $this->isHrScopedRole();
    }

    public function canViewUsers(): bool
    {
        return in_array($this->role(), ['admin', 'hr', 'finance', 'manager', 'dept_head', 'department_head', 'employee'], true);
    }

    public function canManageUsers(): bool
    {
        return $this->isHrScopedRole();
    }

    public function canViewDepartments(): bool
    {
        return in_array($this->role(), ['admin', 'hr', 'finance', 'manager', 'dept_head', 'department_head', 'employee'], true);
    }

    public function canManageDepartments(): bool
    {
        return in_array($this->role(), ['admin'], true) || $this->isHrScopedRole();
    }

    public function isDepartmentScopedUserViewer(): bool
    {
        return in_array($this->role(), ['manager', 'dept_head', 'department_head', 'employee'], true);
    }

    public function canViewAllUsers(): bool
    {
        return in_array($this->role(), ['admin', 'hr', 'finance'], true);
    }

    public function canViewBudgetCategories(): bool
    {
        return in_array($this->role(), ['admin', 'finance'], true);
    }

    public function canManageBudgetCategories(): bool
    {
        return in_array($this->role(), ['finance'], true);
    }

    public function canManageFinancialSetup(): bool
    {
        return $this->canManageBudgetCategories();
    }

    public function canManageWorkflows(): bool
    {
        return in_array($this->role(), ['admin', 'finance', 'dept_head', 'department_head'], true);
    }

    public function canCreateWorkflow(): bool
    {
        return in_array($this->role(), ['admin', 'finance'], true);
    }

    public function canEditWorkflow(): bool
    {
        return in_array($this->role(), ['admin', 'finance', 'dept_head', 'department_head'], true);
    }

    public function canViewWorkflow(): bool
    {
        return $this->canEditWorkflow();
    }

    public function canAccessBudgetMonitor(): bool
    {
        return in_array($this->role(), ['manager', 'dept_head'], true) && $this->departmentName() === 'admin';
    }

    public function canViewOrganizationBudgetUtilization(): bool
    {
        if (in_array($this->role(), ['admin', 'finance'], true)) {
            return true;
        }

        return $this->departmentName() === 'finance';
    }

    public function canAccessFinancialRequests(): bool
    {
        return in_array($this->role(), ['admin', 'finance', 'dept_head', 'manager', 'employee', 'hr'], true);
    }

    public function canAccessRequest(int $ownerUserId, int $requestDepartmentId): bool
    {
        $role = $this->role();
        if ($role === 'admin' || $role === 'finance') {
            return true;
        }

        if (in_array($role, ['employee', 'hr'], true)) {
            return $this->userId() > 0 && $this->userId() === $ownerUserId;
        }

        if (in_array($role, ['manager', 'dept_head'], true)) {
            return $this->departmentId() > 0 && $this->departmentId() === $requestDepartmentId;
        }

        return false;
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
