<?php
class AuthController
{
    public function showLogin(): void
    {
        if (!empty($_SESSION['auth']['is_logged_in'])) {
            header('Location: ?route=home');
            exit;
        }

        $flash = flash_consume();
        $authError = $flash['type'] === 'error' ? (string) $flash['message'] : '';
        $authSuccess = $flash['type'] === 'success' ? (string) $flash['message'] : '';
        $oldEmail = (string) ($_SESSION['old_email'] ?? '');
        $envConfig = $GLOBALS['envConfig'] ?? [];
        $authModel = new AuthModel();
        $credentialHints = $authModel->getCredentialHints();

        require ROOT_PATH . '/views/module-1/login.php';
    }

    public function forbidden(): void
    {
        $isLoggedIn = !empty($_SESSION['auth']['is_logged_in']);
        $pageTitle = 'Access Denied - Expense Register';
        $pageStyles = ['assets/css/dashboard.css', 'assets/css/list.css'];
        $userName = (string) ($_SESSION['auth']['name'] ?? 'User');
        $activeMenu = 'dashboard';
        $errorCode = trim((string) ($_GET['code'] ?? 'unauthorized'));
        $errorMessage = trim((string) ($_GET['message'] ?? 'You do not have permission to access this area.'));
        RbacService::audit('access_denied', ['code' => $errorCode]);

        if ($isLoggedIn) {
            require ROOT_PATH . '/views/templates/header.php';
            require ROOT_PATH . '/views/templates/navbar.php';
            require ROOT_PATH . '/views/templates/sidebar.php';
            require ROOT_PATH . '/views/module-1/forbidden.php';
            require ROOT_PATH . '/views/templates/footer.php';
            return;
        }

        require ROOT_PATH . '/views/templates/header.php';
        require ROOT_PATH . '/views/module-1/forbidden.php';
        require ROOT_PATH . '/views/templates/footer.php';
    }

    public function login(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ?route=dashboard');
            exit;
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $_SESSION['old_email'] = $email;

        if ($email === '' || $password === '') {
            flash_error('Email and password are required.');
            header('Location: ?route=dashboard');
            exit;
        }

        $authModel = new AuthModel();
        $user = null;
        try {
            $user = $authModel->getUserByEmail($email);
        } catch (Throwable $error) {
            flash_error('Unable to validate credentials from database. Please check DB configuration.');
            header('Location: ?route=dashboard');
            exit;
        }

        $isValid = false;
        if ($user) {
            $hash = (string) ($user['password'] ?? '');
            $isValid = password_verify($password, $hash) || hash_equals($hash, $password);
        }

        if (!$isValid) {
            flash_error('Invalid Credentials');
            header('Location: ?route=dashboard');
            exit;
        }

        $sessionRole = strtolower(trim((string) ($user['role'] ?? '')));

        $_SESSION['auth'] = [
            'is_logged_in' => true,
            'user_id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? 'User'),
            'email' => (string) ($user['email'] ?? $email),
            'role' => $sessionRole,
            'department_id' => (int) ($user['department_id'] ?? 0),
            'department_name' => (string) ($user['department_name'] ?? ''),
        ];

        unset($_SESSION['old_email']);
        flash_success('Login successful.');

        header('Location: ?route=home');
        exit;
    }

    public function dashboard(): void
    {
        if (empty($_SESSION['auth']['is_logged_in'])) {
            flash_error('Please login to continue.');
            header('Location: ?route=dashboard');
            exit;
        }

        $userName = (string) ($_SESSION['auth']['name'] ?? 'User');
        $envConfig = $GLOBALS['envConfig'] ?? [];
        $rbac = new RbacService();
        $canViewBudgetUtilization = $rbac->canViewOrganizationBudgetUtilization();

        require ROOT_PATH . '/views/module-1/dashboard.php';
    }

    public function logout(): void
    {
        unset($_SESSION['auth']);
        session_regenerate_id(true);
        flash_success('Logged out successfully.');
        header('Location: ?route=dashboard');
        exit;
    }
}