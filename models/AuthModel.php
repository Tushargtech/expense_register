<?php

class AuthModel
{
    private ?bool $loginAttemptsTableAvailable = null;

    private function normalizeLoginIdentity(string $email): string
    {
        return strtolower(trim($email));
    }

    private function hasLoginAttemptsTable(): bool
    {
        if ($this->loginAttemptsTableAvailable !== null) {
            return $this->loginAttemptsTableAvailable;
        }

        $db = getDB();

        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name'
            );
            $stmt->execute([':table_name' => 'auth_login_attempts']);
            $this->loginAttemptsTableAvailable = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $error) {
            // Keep authentication available even if lockout storage is unavailable.
            $this->loginAttemptsTableAvailable = false;
            error_log('AuthModel::hasLoginAttemptsTable failed: ' . $error->getMessage());
        }

        return $this->loginAttemptsTableAvailable;
    }

    public function getLoginLockStatus(string $email, int $maxAttempts, int $lockoutMinutes): array
    {
        $identity = $this->normalizeLoginIdentity($email);
        $safeMaxAttempts = max(1, $maxAttempts);
        $safeLockoutMinutes = max(1, $lockoutMinutes);

        if ($identity === '') {
            return [
                'is_locked' => false,
                'failed_attempts' => 0,
                'remaining_seconds' => 0,
                'max_attempts' => $safeMaxAttempts,
                'lockout_minutes' => $safeLockoutMinutes,
            ];
        }

        if (!$this->hasLoginAttemptsTable()) {
            return [
                'is_locked' => false,
                'failed_attempts' => 0,
                'remaining_seconds' => 0,
                'max_attempts' => $safeMaxAttempts,
                'lockout_minutes' => $safeLockoutMinutes,
            ];
        }

        $db = getDB();
        $windowSql = max(1, $safeLockoutMinutes);
        try {
            $stmt = $db->prepare(
                "SELECT
                    COUNT(*) AS failed_attempts,
                    MAX(attempted_at) AS last_failed_at
                 FROM auth_login_attempts
                 WHERE attempt_email = :attempt_email
                   AND attempt_success = 0
                   AND attempted_at >= DATE_SUB(NOW(), INTERVAL {$windowSql} MINUTE)"
            );
            $stmt->execute([':attempt_email' => $identity]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $error) {
            $this->loginAttemptsTableAvailable = false;
            error_log('AuthModel::getLoginLockStatus failed: ' . $error->getMessage());

            return [
                'is_locked' => false,
                'failed_attempts' => 0,
                'remaining_seconds' => 0,
                'max_attempts' => $safeMaxAttempts,
                'lockout_minutes' => $safeLockoutMinutes,
            ];
        }

        $failedAttempts = (int) ($row['failed_attempts'] ?? 0);
        $lastFailedAt = (string) ($row['last_failed_at'] ?? '');
        $remainingSeconds = 0;

        if ($failedAttempts >= $safeMaxAttempts && $lastFailedAt !== '') {
            $lockoutEndsAt = strtotime($lastFailedAt . ' +' . $safeLockoutMinutes . ' minutes');
            if ($lockoutEndsAt !== false) {
                $remainingSeconds = max(0, $lockoutEndsAt - time());
            }
        }

        return [
            'is_locked' => $failedAttempts >= $safeMaxAttempts && $remainingSeconds > 0,
            'failed_attempts' => $failedAttempts,
            'remaining_seconds' => $remainingSeconds,
            'max_attempts' => $safeMaxAttempts,
            'lockout_minutes' => $safeLockoutMinutes,
        ];
    }

    public function recordLoginAttempt(string $email, bool $success): void
    {
        $identity = $this->normalizeLoginIdentity($email);
        if ($identity === '') {
            return;
        }

        if (!$this->hasLoginAttemptsTable()) {
            return;
        }

        $db = getDB();
        try {
            $stmt = $db->prepare(
                'INSERT INTO auth_login_attempts (attempt_email, attempt_success, attempted_at) VALUES (:attempt_email, :attempt_success, NOW())'
            );
            $stmt->execute([
                ':attempt_email' => $identity,
                ':attempt_success' => $success ? 1 : 0,
            ]);
        } catch (Throwable $error) {
            $this->loginAttemptsTableAvailable = false;
            error_log('AuthModel::recordLoginAttempt failed: ' . $error->getMessage());
        }
    }

    public function clearFailedLoginAttempts(string $email): void
    {
        $identity = $this->normalizeLoginIdentity($email);
        if ($identity === '') {
            return;
        }

        if (!$this->hasLoginAttemptsTable()) {
            return;
        }

        $db = getDB();
        try {
            $stmt = $db->prepare('DELETE FROM auth_login_attempts WHERE attempt_email = :attempt_email AND attempt_success = 0');
            $stmt->execute([':attempt_email' => $identity]);
        } catch (Throwable $error) {
            $this->loginAttemptsTableAvailable = false;
            error_log('AuthModel::clearFailedLoginAttempts failed: ' . $error->getMessage());
        }
    }

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
                COALESCE(users.password_must_reset, 0) AS password_must_reset,
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