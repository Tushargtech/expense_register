<?php
class AuthController
{
    private function authPolicy(): array
    {
        $appConfig = $GLOBALS['envConfig']['app'] ?? [];

        return [
            'max_attempts' => max(1, (int) ($appConfig['auth_max_login_attempts'] ?? AUTH_MAX_LOGIN_ATTEMPTS)),
            'lockout_minutes' => max(1, (int) ($appConfig['auth_lockout_minutes'] ?? AUTH_LOCKOUT_MINUTES)),
        ];
    }

    private function formatLockoutMessage(int $remainingSeconds): string
    {
        $remainingMinutes = (int) ceil(max(0, $remainingSeconds) / 60);
        return 'Too many failed login attempts. Please try again in ' . max(1, $remainingMinutes) . ' minute(s).';
    }

    public function showLogin(): void
    {
        if (!empty($_SESSION['auth']['is_logged_in'])) {
            header('Location: ?route=dashboard');
            exit;
        }

        $flash = flash_consume();
        $authError = $flash['type'] === 'error' ? (string) $flash['message'] : '';
        $authSuccess = $flash['type'] === 'success' ? (string) $flash['message'] : '';
        $oldEmail = (string) ($_SESSION['old_email'] ?? '');
        $envConfig = $GLOBALS['envConfig'] ?? [];
        $authModel = new AuthModel();
        $credentialHints = $authModel->getCredentialHints();

        require ROOT_PATH . '/views/templates/app_layout.php';
        renderAppLayoutStart([
            'pageTitle' => 'Login - Expense Register',
            'pageStyles' => ['assets/css/app.css'],
            'bodyClass' => 'bg-light',
            'includeChrome' => false,
        ]);

        require ROOT_PATH . '/views/main/login.php';

        renderAppLayoutEnd();
    }

    public function forbidden(): void
    {
        $isLoggedIn = !empty($_SESSION['auth']['is_logged_in']);
        $pageTitle = 'Access Denied - Expense Register';
        $pageStyles = ['assets/css/app.css'];
        $userName = (string) ($_SESSION['auth']['name'] ?? 'User');
        $activeMenu = 'dashboard';
        $errorCode = trim((string) ($_GET['code'] ?? 'unauthorized'));
        $errorMessage = trim((string) ($_GET['message'] ?? 'You do not have permission to access this area.'));
        RbacService::audit('access_denied', ['code' => $errorCode]);

        if ($isLoggedIn) {
            require ROOT_PATH . '/views/templates/app_layout.php';
            renderAppLayoutStart([
                'pageTitle' => $pageTitle,
                'pageStyles' => $pageStyles,
                'activeMenu' => $activeMenu,
                'includeChrome' => true,
            ]);
            require ROOT_PATH . '/views/main/forbidden.php';
            renderAppLayoutEnd();
            return;
        }

        require ROOT_PATH . '/views/templates/app_layout.php';
        renderAppLayoutStart([
            'pageTitle' => $pageTitle,
            'pageStyles' => $pageStyles,
            'includeChrome' => false,
        ]);
        require ROOT_PATH . '/views/main/forbidden.php';
        renderAppLayoutEnd();
    }

    public function login(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ?route=login');
            exit;
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $_SESSION['old_email'] = $email;

        if ($email === '' || $password === '') {
            flash_error('Email and password are required.');
            header('Location: ?route=login');
            exit;
        }

        $authModel = new AuthModel();
        $policy = $this->authPolicy();

        $lockStatus = $authModel->getLoginLockStatus($email, $policy['max_attempts'], $policy['lockout_minutes']);
        if (!empty($lockStatus['is_locked'])) {
            flash_error($this->formatLockoutMessage((int) ($lockStatus['remaining_seconds'] ?? 0)));
            header('Location: ?route=login');
            exit;
        }

        $user = null;
        try {
            $user = $authModel->getUserByEmail($email);
        } catch (Throwable $error) {
            flash_error('Unable to validate credentials from database. Please check DB configuration.');
            header('Location: ?route=login');
            exit;
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
                flash_error($this->formatLockoutMessage((int) ($lockStatus['remaining_seconds'] ?? 0)));
            } else {
                $remainingAttempts = max(0, (int) ($policy['max_attempts'] - ((int) ($lockStatus['failed_attempts'] ?? 0))));
                flash_error('Invalid Credentials. Remaining attempts before lockout: ' . $remainingAttempts . '.');
            }

            header('Location: ?route=login');
            exit;
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

        // Check if user must reset password (first-time login or admin-required)
        if (!empty($user['force_password_change']) || !empty($user['password_must_reset'])) {
            $resetModel = new PasswordResetModel();
            $token = $resetModel->createResetToken((int) ($user['id'] ?? 0), 120); // 2 hours
            if ($token) {
                flash_success('Please set your new password.');
                header('Location: ' . buildCleanRouteUrl('password-reset', ['token' => $token]));
                exit;
            }
        }

        flash_success('Login successful.');

        header('Location: ?route=dashboard');
        exit;
    }

    public function dashboard(): void
    {
        if (empty($_SESSION['auth']['is_logged_in'])) {
            flash_error('Please login to continue.');
            header('Location: ?route=login');
            exit;
        }

        $userName = (string) ($_SESSION['auth']['name'] ?? 'User');
        $envConfig = $GLOBALS['envConfig'] ?? [];
        $rbac = new RbacService();
        $canViewBudgetUtilization = $rbac->canViewOrganizationBudgetUtilization();
        $expensesModel = new ExpenseModel();

        $dashboardKpis = $expensesModel->getDashboardKpis($rbac);
        $recentActivity = $expensesModel->getTodayRecentActivity($rbac, 12);
        $isDepartmentHead = $rbac->isDepartmentHead();
        
        // Fetch department budget data for department heads
        $departmentBudgetAllocated = 0;
        $departmentBudgetRemaining = 0;
        if ($isDepartmentHead) {
            $departmentId = $rbac->departmentId();
            if ($departmentId > 0) {
                $budgetMonitorModel = new BudgetMonitorModel();
                $budgetRows = $budgetMonitorModel->getMonitorRows([], $departmentId);

                $budgetAlertService = new BudgetMonitorController();
                if (method_exists($budgetAlertService, 'dispatchBudgetThresholdAlerts')) {
                    $budgetAlertService->dispatchBudgetThresholdAlerts($budgetRows);
                }
                
                $totalAllocated = 0;
                $totalSpent = 0;
                foreach ($budgetRows as $row) {
                    $totalAllocated += (float) ($row['budget_allocated_amount'] ?? 0);
                    $totalSpent += (float) ($row['budget_spent_amount'] ?? 0);
                }
                
                $departmentBudgetAllocated = $totalAllocated;
                $departmentBudgetRemaining = max(0, $totalAllocated - $totalSpent);
            }
        }

        require ROOT_PATH . '/views/main/dashboard.php';
    }

    public function logout(): void
    {
        unset($_SESSION['auth']);
        session_regenerate_id(true);
        flash_success('Logged out successfully.');
        header('Location: ?route=login');
        exit;
    }
}