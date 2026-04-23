<?php

class PasswordResetController
{
    private PasswordResetModel $resetModel;
    private UserModel $userModel;
    private MailService $mailService;

    public function __construct()
    {
        $this->resetModel = new PasswordResetModel();
        $this->userModel = new UserModel();
        $this->mailService = new MailService();
    }

    /**
     * Show reset password form
     */
    public function showResetForm(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $error = '';
        $user = null;

        if ($token === '') {
            $error = 'Invalid reset link. Please request a new password reset.';
        } else {
            $user = $this->resetModel->getUserByResetToken($token);
            if ($user === null) {
                $error = 'This password reset link has expired or is invalid. Please request a new one.';
            }
        }

        $pageTitle = 'Reset Password - Expense Register';
        $pageStyles = ['assets/css/app.css'];
        $bodyClass = 'bg-light';

        require ROOT_PATH . '/views/templates/app_layout.php';
        renderAppLayoutStart([
            'pageTitle' => $pageTitle,
            'pageStyles' => $pageStyles,
            'bodyClass' => $bodyClass,
            'includeChrome' => true,
            'showNavbarControls' => false,
            'showSidebar' => false,
        ]);

        require ROOT_PATH . '/views/Main/password_reset.php';

        renderAppLayoutEnd();
    }

    /**
     * Process password reset
     */
    public function resetPassword(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . buildCleanRouteUrl('login'));
            exit;
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        // Validate token
        $user = $this->resetModel->getUserByResetToken($token);
        if ($user === null) {
            flash_error('Invalid or expired reset link. Please request a new password reset.');
            header('Location: ' . buildCleanRouteUrl('forgot-password'));
            exit;
        }

        // Validate passwords
        if ($newPassword === '' || $confirmPassword === '') {
            flash_error('Please enter a new password.');
            header('Location: ' . buildCleanRouteUrl('password-reset', ['token' => $token]));
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            flash_error('Passwords do not match.');
            header('Location: ' . buildCleanRouteUrl('password-reset', ['token' => $token]));
            exit;
        }

        if (strlen($newPassword) < 8) {
            flash_error('Password must be at least 8 characters long.');
            header('Location: ' . buildCleanRouteUrl('password-reset', ['token' => $token]));
            exit;
        }

        // Update password
        $userId = (int) ($user['user_id'] ?? 0);
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        if (!$this->userModel->updateUserPassword($userId, $hashedPassword)) {
            flash_error('Failed to update password. Please try again.');
            header('Location: ' . buildCleanRouteUrl('password-reset', ['token' => $token]));
            exit;
        }

        // Mark token as used and invalidate other tokens
        $this->resetModel->markTokenAsUsed($token);
        $this->resetModel->invalidateUserTokens($userId);

        // Clear force password change flag if set
        $this->userModel->clearForcePasswordChange($userId);

        flash_success('Your password has been reset successfully. You can now log in with your new password.');
        header('Location: ' . buildCleanRouteUrl('login'));
        exit;
    }

    /**
     * Show forgot password form
     */
    public function showForgotPasswordForm(): void
    {
        $oldEmail = isset($_SESSION['forgot_email']) ? (string) $_SESSION['forgot_email'] : '';
        unset($_SESSION['forgot_email']);

        $pageTitle = 'Forgot Password - Expense Register';
        $pageStyles = ['assets/css/app.css'];
        $bodyClass = 'bg-light';

        require ROOT_PATH . '/views/templates/app_layout.php';
        renderAppLayoutStart([
            'pageTitle' => $pageTitle,
            'pageStyles' => $pageStyles,
            'bodyClass' => $bodyClass,
            'includeChrome' => true,
            'showNavbarControls' => false,
            'showSidebar' => false,
        ]);

        require ROOT_PATH . '/views/Main/forgot_password.php';

        renderAppLayoutEnd();
    }

    /**
     * Process forgot password request
     */
    public function sendForgotPasswordEmail(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ' . buildCleanRouteUrl('login'));
            exit;
        }

        $email = trim((string) ($_POST['email'] ?? ''));

        if ($email === '') {
            flash_error('Please enter your email address.');
            $_SESSION['forgot_email'] = $email;
            header('Location: ' . buildCleanRouteUrl('forgot-password'));
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_error('Please enter a valid email address.');
            $_SESSION['forgot_email'] = $email;
            header('Location: ' . buildCleanRouteUrl('forgot-password'));
            exit;
        }

        // Create reset token and send email
        $userData = $this->resetModel->createResetTokenByEmail($email);

        if ($userData === null) {
            // Don't reveal if email exists (security)
            flash_success('If an account exists with that email, a password reset link has been sent.');
            header('Location: ' . buildCleanRouteUrl('login'));
            exit;
        }

        // Send password reset email
        $resetLink = $this->getResetLink($userData['token']);
        $sent = $this->mailService->sendPasswordResetEmail(
            $userData['user_email'],
            $userData['user_name'],
            $resetLink,
            60
        );

        if (!$sent) {
            flash_error('Failed to send password reset email. Please try again later.');
            $_SESSION['forgot_email'] = $email;
            header('Location: ' . buildCleanRouteUrl('forgot-password'));
            exit;
        }

        flash_success('A password reset link has been sent to your email address.');
        header('Location: ' . buildCleanRouteUrl('login'));
        exit;
    }

    /**
     * Generate reset password link
     */
    private function getResetLink(string $token): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $basePath = '/expense_register';

        return "{$scheme}://{$host}{$basePath}/password-reset?token=" . urlencode($token);
    }
}
