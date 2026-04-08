<?php
class AuthController
{
    public function showLogin(): void
    {
        if (!empty($_SESSION['auth']['is_logged_in'])) {
            header('Location: ?route=home');
            exit;
        }

        $authError = (string) ($_SESSION['auth_error'] ?? '');
        $authSuccess = (string) ($_SESSION['auth_success'] ?? '');
        $oldEmail = (string) ($_SESSION['old_email'] ?? '');
        $envConfig = $GLOBALS['envConfig'] ?? [];

        unset($_SESSION['auth_error'], $_SESSION['auth_success']);

        require ROOT_PATH . '/views/module-1/login.php';
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
            $_SESSION['auth_error'] = 'Email and password are required.';
            header('Location: ?route=dashboard');
            exit;
        }

        $authModel = new AuthModel();
        $user = null;
        try {
            $user = $authModel->getUserByEmail($email);
        } catch (Throwable $error) {
            $_SESSION['auth_error'] = 'Unable to validate credentials from database. Please check DB configuration.';
            header('Location: ?route=dashboard');
            exit;
        }

        $isValid = false;
        if ($user) {
            $hash = (string) ($user['password'] ?? '');
            $isValid = password_verify($password, $hash) || hash_equals($hash, $password);
        }

        if (!$isValid) {
            $_SESSION['auth_error'] = 'Invalid Credentials';
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
        ];

        unset($_SESSION['old_email']);
        $_SESSION['auth_success'] = 'Login successful.';

        header('Location: ?route=home');
        exit;
    }

    public function dashboard(): void
    {
        if (empty($_SESSION['auth']['is_logged_in'])) {
            $_SESSION['auth_error'] = 'Please login to continue.';
            header('Location: ?route=dashboard');
            exit;
        }

        $userName = (string) ($_SESSION['auth']['name'] ?? 'User');
        $envConfig = $GLOBALS['envConfig'] ?? [];

        require ROOT_PATH . '/views/module-1/dashboard.php';
    }

    public function logout(): void
    {
        unset($_SESSION['auth']);
        session_regenerate_id(true);
        $_SESSION['auth_success'] = 'Logged out successfully.';
        header('Location: ?route=dashboard');
        exit;
    }
}