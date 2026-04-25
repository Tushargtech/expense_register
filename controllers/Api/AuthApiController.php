<?php

class AuthApiController extends ApiBaseController
{
    private function authPolicy(): array
    {
        $appConfig = $GLOBALS['envConfig']['app'] ?? [];

        return [
            'max_attempts' => max(1, (int) ($appConfig['auth_max_login_attempts'] ?? AUTH_MAX_LOGIN_ATTEMPTS)),
            'lockout_minutes' => max(1, (int) ($appConfig['auth_lockout_minutes'] ?? AUTH_LOCKOUT_MINUTES)),
        ];
    }

    private function lockoutMessage(int $remainingSeconds): string
    {
        $remainingMinutes = (int) ceil(max(0, $remainingSeconds) / 60);
        return 'Too many failed login attempts. Please try again in ' . max(1, $remainingMinutes) . ' minute(s).';
    }

    public function login(): void
    {
        if ($this->method() !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        $input = $this->input();
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->jsonError('Email and password are required.', 422);
        }

        $authModel = new AuthModel();
        $policy = $this->authPolicy();
        $lockStatus = $authModel->getLoginLockStatus($email, $policy['max_attempts'], $policy['lockout_minutes']);

        if (!empty($lockStatus['is_locked'])) {
            $this->jsonError($this->lockoutMessage((int) ($lockStatus['remaining_seconds'] ?? 0)), 429);
        }

        $user = null;

        try {
            $user = $authModel->getUserByEmail($email);
        } catch (Throwable $error) {
            $this->jsonError('Unable to validate credentials from database.', 500);
        }

        $isValid = false;
        if ($user) {
            $hash = (string) ($user['password'] ?? '');
            $isValid = password_verify($password, $hash) || hash_equals($hash, $password);
        }

        if (!$isValid) {
            $authModel->recordLoginAttempt($email, false);
            $lockStatus = $authModel->getLoginLockStatus($email, $policy['max_attempts'], $policy['lockout_minutes']);

            if (!empty($lockStatus['is_locked'])) {
                $this->jsonError($this->lockoutMessage((int) ($lockStatus['remaining_seconds'] ?? 0)), 429);
            }

            $remainingAttempts = max(0, (int) ($policy['max_attempts'] - ((int) ($lockStatus['failed_attempts'] ?? 0))));
            $this->jsonError('Invalid credentials. Remaining attempts before lockout: ' . $remainingAttempts . '.', 401);
        }

        $authModel->recordLoginAttempt($email, true);
        $authModel->clearFailedLoginAttempts($email);

        $sessionRole = strtolower(trim((string) ($user['role'] ?? '')));
        $_SESSION['auth'] = [
            'is_logged_in' => true,
            'user_id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? 'User'),
            'email' => (string) ($user['email'] ?? $email),
            'last_activity_at' => time(),
            'role' => $sessionRole,
            'base_role' => $sessionRole,
            'is_manager' => (bool) ($user['is_manager'] ?? false),
            'is_department_head' => (bool) ($user['is_department_head'] ?? false),
            'role_permissions' => $user['role_permissions'] ?? null,
            'department_id' => (int) ($user['department_id'] ?? 0),
            'department_name' => (string) ($user['department_name'] ?? ''),
        ];

        unset($_SESSION['old_email']);
        RbacService::audit('api_login', ['user_id' => (int) ($user['id'] ?? 0)]);

        $this->jsonSuccess([
            'user' => [
                'user_id' => (int) ($user['id'] ?? 0),
                'name' => (string) ($user['name'] ?? 'User'),
                'email' => (string) ($user['email'] ?? $email),
                'role' => $sessionRole,
                'base_role' => $sessionRole,
                'is_manager' => (bool) ($user['is_manager'] ?? false),
                'is_department_head' => (bool) ($user['is_department_head'] ?? false),
                'department_id' => (int) ($user['department_id'] ?? 0),
                'department_name' => (string) ($user['department_name'] ?? ''),
            ],
        ], [], 200);
    }

    public function logout(): void
    {
        unset($_SESSION['auth']);
        session_regenerate_id(true);
        $this->jsonSuccess(['message' => 'Logged out successfully.']);
    }

    public function me(): void
    {
        $this->ensureAuthenticated();
        $auth = $this->authenticatedUser();
        $this->jsonSuccess(['auth' => $auth]);
    }
}