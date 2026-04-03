<?php

final class AuthController extends BaseController
{
    public function __construct(private readonly AuthModel $authModel)
    {
    }

    /**
     * GET: login page.
     */
    public function showLogin(): void
    {
        $this->render('auth/login', [
            'title' => 'Expense Register - Login',
            'csrfToken' => $this->csrfToken(),
            'error' => $this->pullFlash('error'),
            'oldEmail' => $this->pullFlash('old_email') ?? '',
        ]);
    }

    /**
     * POST: authenticate user and open dashboard.
     */
    public function login(array $postData): void
    {
        $email = trim((string)($postData['email'] ?? ''));
        $password = (string)($postData['password'] ?? '');
        $csrfToken = (string)($postData['csrf_token'] ?? '');

        if (!$this->isValidCsrf($csrfToken)) {
            $this->setFlash('error', 'Invalid request token. Please try again.');
            $this->redirect('login');
        }

        $this->setFlash('old_email', $email);

        if ($email === '' || $password === '') {
            $this->setFlash('error', 'Email and password are required.');
            $this->redirect('login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->setFlash('error', 'Please enter a valid email address.');
            $this->redirect('login');
        }

        $user = $this->authModel->findByEmail($email);
        if ($user === null || !password_verify($password, (string)$user['user_password_hash'])) {
            $this->setFlash('error', 'Invalid email or password.');
            $this->redirect('login');
        }

        session_regenerate_id(true);

        $_SESSION['auth_user'] = [
            'id' => (int)$user['user_id'],
            'name' => (string)$user['user_name'],
            'email' => (string)$user['user_email'],
            'role' => (string)$user['user_role'],
            'department_id' => $user['department_id'] !== null ? (int)$user['department_id'] : null,
        ];

        unset($_SESSION['_flash']['old_email']);
        $this->setFlash('success', 'Login successful.');
        $this->redirect('dashboard');
    }

    /**
     * GET: dashboard page (requires logged-in user).
     */
    public function dashboard(): void
    {
        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('login');
        }

        $this->render('dashboard', [
            'authUser' => $user,
            'success' => $this->pullFlash('success'),
        ]);
    }

    public function logout(): void
    {
        unset($_SESSION['auth_user']);
        session_regenerate_id(true);
        $this->setFlash('success', 'You have been logged out.');
        $this->redirect('login');
    }

    public function isAuthenticated(): bool
    {
        return $this->currentUser() !== null;
    }

    public function currentUser(): ?array
    {
        return $_SESSION['auth_user'] ?? null;
    }

    private function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION['csrf_token'];
    }

    private function isValidCsrf(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals((string)$_SESSION['csrf_token'], $token);
    }

    private function setFlash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    private function pullFlash(string $key): ?string
    {
        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }

        $message = (string)$_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $message;
    }
}
