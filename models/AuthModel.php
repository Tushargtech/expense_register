<?php

class AuthModel
{
    private function decodePermissions(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeBaseRole(string $role): string
    {
        $normalizedRole = strtolower(trim($role));

        return match ($normalizedRole) {
            'admin', 'finance', 'hr', 'employee' => $normalizedRole,
            'hr_manager', 'hr_department_head', 'hr_dept_head' => 'hr',
            'manager', 'department_head', 'dept_head', 'depthead' => 'employee',
            default => 'employee',
        };
    }

    private function buildDerivedPermissions(array $basePermissions, string $effectiveRole): array
    {
        if ($effectiveRole === 'manager') {
            $overrides = [
                'budget_categories' => ['view' => true],
                'budget_monitor' => ['view' => true],
                'workflows' => ['view' => true],
                'expenses' => ['review' => true],
            ];

            return array_replace_recursive($basePermissions, $overrides);
        }

        if ($effectiveRole === 'department_head') {
            $overrides = [
                'budget_categories' => ['view' => true],
                'budget_monitor' => ['view' => true],
                'workflows' => ['list' => true, 'view' => true],
                'expenses' => ['review' => true],
            ];

            return array_replace_recursive($basePermissions, $overrides);
        }

        return $basePermissions;
    }

    public function getUserByEmail(string $email): array|false
    {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT
                user_id AS id,
                user_name AS name,
                user_email AS email,
                user_password_hash AS password,
                user_role AS base_role,
                CASE
                    WHEN users.user_role IN ('admin', 'finance', 'hr', 'employee') THEN users.user_role
                    WHEN users.user_role IN ('hr_manager', 'hr_department_head', 'hr_dept_head') THEN 'hr'
                    WHEN users.user_role IN ('manager', 'department_head', 'dept_head', 'depthead') THEN 'employee'
                    ELSE 'employee'
                END AS role,
                r.role_permissions AS base_role_permissions,
                department_id,
                d.department_name AS department_name,
                EXISTS(
                    SELECT 1
                    FROM departments dh
                    WHERE dh.department_head_user_id = users.user_id
                    LIMIT 1
                ) AS is_department_head,
                EXISTS(
                    SELECT 1
                    FROM users m
                    WHERE m.manager_id = users.user_id
                      AND m.user_is_active = 1
                    LIMIT 1
                ) AS is_manager
             FROM users
             LEFT JOIN departments d ON d.id = users.department_id
             LEFT JOIN roles r ON r.role_slug = CASE
                WHEN users.user_role IN ('admin', 'finance', 'hr', 'employee') THEN users.user_role
                WHEN users.user_role IN ('hr_manager', 'hr_department_head', 'hr_dept_head') THEN 'hr'
                WHEN users.user_role IN ('manager', 'department_head', 'dept_head', 'depthead') THEN 'employee'
                ELSE 'employee'
             END
             WHERE user_email = ? AND user_is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$email]);

		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row === false) {
			return false;
		}

		$baseRole = $this->normalizeBaseRole((string) ($row['base_role'] ?? 'employee'));
		$effectiveRole = $baseRole;
		if (!in_array($baseRole, ['admin', 'finance'], true)) {
			if ((int) ($row['is_department_head'] ?? 0) === 1) {
				$effectiveRole = 'department_head';
			} elseif ((int) ($row['is_manager'] ?? 0) === 1) {
				$effectiveRole = 'manager';
			}
		}

		$basePermissions = $this->decodePermissions($row['base_role_permissions'] ?? null);
		$row['role'] = $baseRole;
		$row['effective_role'] = $effectiveRole;
		$row['role_permissions'] = $this->buildDerivedPermissions($basePermissions, $effectiveRole);
		$row['is_manager'] = (int) ($row['is_manager'] ?? 0) === 1;
		$row['is_department_head'] = (int) ($row['is_department_head'] ?? 0) === 1;

		return $row;
    }

    public function getCredentialHints(int $limit = 8): array
    {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT
                user_name,
                user_email,
                user_role,
                user_password_hash
             FROM users
             WHERE user_is_active = 1
               ORDER BY user_role ASC, user_name ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hints = [];
        foreach ($rows as $row) {
            $hints[] = [
                'name' => (string) ($row['user_name'] ?? ''),
                'email' => (string) ($row['user_email'] ?? ''),
                'role' => strtolower(trim((string) ($row['user_role'] ?? 'employee'))),
                'password_hint' => 'Use assigned password',
            ];
        }

        return $hints;
    }
}