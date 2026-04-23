<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private PHPMailer $mailer;
    private bool $configured = false;

    public function __construct()
    {
        try {
            $this->mailer = new PHPMailer(true);
            $this->configure();
        } catch (Exception $e) {
            error_log('PHPMailer initialization error: ' . $e->getMessage());
            $this->configured = false;
        }
    }

    private function configure(): void
    {
        try {
            $envConfig = $GLOBALS['envConfig'] ?? (defined('ROOT_PATH') ? require ROOT_PATH . '/configs/env.php' : []);
            $mailConfig = isset($envConfig['mail']) && is_array($envConfig['mail']) ? $envConfig['mail'] : [];

            $host = (string) ($mailConfig['host'] ?? 'smtp.gmail.com');
            $port = (int) ($mailConfig['port'] ?? 587);
            $encryption = strtolower(trim((string) ($mailConfig['encryption'] ?? 'tls')));
            $username = (string) ($mailConfig['username'] ?? '');
            $rawPassword = (string) ($mailConfig['password'] ?? '');
            $password = preg_replace('/\s+/', '', $rawPassword) ?: '';
            $fromAddress = (string) ($mailConfig['from_address'] ?? $username);
            $fromName = (string) ($mailConfig['from_name'] ?? 'Expense Register Team');

            if ($username === '' || $password === '' || $fromAddress === '') {
                throw new Exception('SMTP username/password/from_address is missing in mail config.');
            }

            // SMTP Configuration
            $this->mailer->isSMTP();
            $this->mailer->Host = $host;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $username;
            $this->mailer->Password = $password;
            $this->mailer->SMTPSecure = $encryption === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = $port;
            
            // Set default from email
            $this->mailer->setFrom($fromAddress, $fromName);
            $this->mailer->CharSet = 'UTF-8';
            
            $this->configured = true;
        } catch (Exception $e) {
            error_log('PHPMailer configuration error: ' . $e->getMessage());
            $this->configured = false;
        }
    }

    private function renderEmailTemplate(string $template, array $data): string
    {
        $templateFile = defined('ROOT_PATH') ? ROOT_PATH . '/views/templates/email.php' : '';

        if ($templateFile !== '' && file_exists($templateFile)) {
            require_once $templateFile;
        }

        if (function_exists('renderEmailTemplate')) {
            return renderEmailTemplate($template, $data);
        }

        return '';
    }

    /**
     * Send request submission confirmation email
     */
    public function sendRequestSubmittedEmail(
        string $recipientEmail,
        string $employeeName,
        string $requestTypeLabel,
        string $requestNo,
        string $currency,
        string $amount,
        string $budgetHead,
        string $description,
        string $requestLink
    ): bool {
        if (!$this->configured) {
            error_log('Mail service not configured');
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Request Submitted Successfully: ' . $requestTypeLabel . ' #' . $requestNo;

            $htmlBody = $this->renderEmailTemplate('request_submission', [
                'employee_name' => $employeeName,
                'request_type_label' => $requestTypeLabel,
                'request_no' => $requestNo,
                'currency' => $currency,
                'amount' => $amount,
                'budget_head' => $budgetHead,
                'description' => $description,
                'request_link' => $requestLink,
            ]);

            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = strip_tags($htmlBody);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('Request submission email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send approver action required email
     */
    public function sendRequestActionRequiredEmail(
        string $recipientEmail,
        string $approverName,
        string $employeeName,
        string $previousActor,
        string $requestTypeLabel,
        string $requestNo,
        string $currency,
        string $amount,
        string $budgetHead,
        string $description,
        string $requestLink,
        int $approvalTimeout,
        bool $isNewSubmission
    ): bool {
        if (!$this->configured) {
            error_log('Mail service not configured');
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Action Required: ' . $requestTypeLabel . ' #' . $requestNo . ' Pending Your Review';

            $htmlBody = $this->renderEmailTemplate('request_action_required', [
                'approver_name' => $approverName,
                'employee_name' => $employeeName,
                'previous_actor' => $previousActor,
                'request_type_label' => $requestTypeLabel,
                'request_no' => $requestNo,
                'currency' => $currency,
                'amount' => $amount,
                'budget_head' => $budgetHead,
                'description' => $description,
                'request_link' => $requestLink,
                'approval_timeout' => $approvalTimeout,
                'is_new_submission' => $isNewSubmission,
            ]);

            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = strip_tags($htmlBody);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('Request action required email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send final request approved email to requester
     */
    public function sendRequestApprovedEmail(
        string $recipientEmail,
        string $employeeName,
        string $requestTypeLabel,
        string $requestNo,
        string $actorName,
        ?string $comments,
        string $currency,
        string $amount,
        string $budgetHead,
        string $description,
        string $requestLink
    ): bool {
        if (!$this->configured) {
            error_log('Mail service not configured');
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Request Approved: ' . $requestTypeLabel . ' #' . $requestNo;

            $htmlBody = $this->renderEmailTemplate('request_approved', [
                'employee_name' => $employeeName,
                'request_type_label' => $requestTypeLabel,
                'request_no' => $requestNo,
                'actor_name' => $actorName,
                'comments' => $comments ?? '',
                'currency' => $currency,
                'amount' => $amount,
                'budget_head' => $budgetHead,
                'description' => $description,
                'request_link' => $requestLink,
            ]);

            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = strip_tags($htmlBody);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('Request approved email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send request rejected email to requester
     */
    public function sendRequestRejectedEmail(
        string $recipientEmail,
        string $employeeName,
        string $requestTypeLabel,
        string $requestNo,
        string $actorName,
        string $stepTitle,
        ?string $comments,
        string $requestLink
    ): bool {
        if (!$this->configured) {
            error_log('Mail service not configured');
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Request Rejected: ' . $requestTypeLabel . ' #' . $requestNo;

            $htmlBody = $this->renderEmailTemplate('request_rejected', [
                'employee_name' => $employeeName,
                'request_type_label' => $requestTypeLabel,
                'request_no' => $requestNo,
                'actor_name' => $actorName,
                'step_title' => $stepTitle,
                'comments' => $comments ?? '',
                'request_link' => $requestLink,
            ]);

            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = strip_tags($htmlBody);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('Request rejected email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send ticket reassignment email to the new approver
     */
    public function sendTicketReassignmentEmail(
        string $recipientEmail,
        string $newApproverName,
        string $previousActor,
        string $requestNo,
        ?string $comments,
        string $requestLink
    ): bool {
        if (!$this->configured) {
            error_log('Mail service not configured');
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Action Required: Request #' . $requestNo . ' Assigned to You';

            $htmlBody = $this->renderEmailTemplate('ticket_reassignment', [
                'new_approver_name' => $newApproverName,
                'previous_actor' => $previousActor,
                'request_no' => $requestNo,
                'comments' => $comments ?? '',
                'request_link' => $requestLink,
            ]);

            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = strip_tags($htmlBody);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('Ticket reassignment email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send budget threshold alert email
     */
    public function sendBudgetThresholdAlertEmail(
        string $recipientEmail,
        string $departmentName,
        string $budgetHead,
        string $usagePercent,
        string $currency,
        string $totalLimit,
        string $usedAmount
    ): bool {
        if (!$this->configured) {
            error_log('Mail service not configured');
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'URGENT: Budget Utilization Alert – ' . $departmentName;

            $htmlBody = $this->renderEmailTemplate('budget_threshold_alert', [
                'department_name' => $departmentName,
                'budget_head' => $budgetHead,
                'usage_percent' => $usagePercent,
                'currency' => $currency,
                'total_limit' => $totalLimit,
                'used_amount' => $usedAmount,
            ]);

            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = strip_tags($htmlBody);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('Budget threshold alert email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send budget update notification to department head
     */
    public function sendBudgetUpdateNotificationEmail(
        string $recipientEmail,
        string $departmentHeadName,
        string $departmentName,
        string $budgetHead,
        string $fiscalYear,
        string $fiscalPeriod,
        string $currency,
        string $totalLimit,
        string $previousLimit,
        string $differenceAmount,
        string $effectiveDate,
        string $actionType
    ): bool {
        if (!$this->configured) {
            error_log('Mail service not configured');
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Budget ' . ucfirst($actionType) . ' Notification - ' . $departmentName . ' (FY ' . $fiscalYear . ')';

            $htmlBody = $this->renderEmailTemplate('budget_update_notification', [
                'department_head_name' => $departmentHeadName,
                'department_name' => $departmentName,
                'budget_head' => $budgetHead,
                'fiscal_year' => $fiscalYear,
                'fiscal_period' => $fiscalPeriod,
                'currency' => $currency,
                'total_limit' => $totalLimit,
                'previous_limit' => $previousLimit,
                'difference_amount' => $differenceAmount,
                'effective_date' => $effectiveDate,
                'action_type' => $actionType,
            ]);

            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = strip_tags($htmlBody);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('Budget update notification email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(string $recipientEmail, string $employeeName, string $resetLink, int $expiryMinutes = 60): bool
    {
        if (!$this->configured) {
            error_log('Mail service not configured');
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Secure Link to Reset Your Expense Portal Password';

            $htmlBody = $this->getPasswordResetTemplate($employeeName, $resetLink, $expiryMinutes);
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = strip_tags($htmlBody);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('Password reset email error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send new user account creation email
     */
    public function sendNewUserEmail(string $recipientEmail, string $employeeName, string $temporaryPassword, string $loginLink, int $expiryMinutes = 60): bool
    {
        if (!$this->configured) {
            error_log('Mail service not configured');
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Your Expense Portal Account Has Been Created';

            $htmlBody = $this->getNewUserTemplate($employeeName, $recipientEmail, $temporaryPassword, $loginLink, $expiryMinutes);
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = strip_tags($htmlBody);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('New user email error: ' . $e->getMessage());
            return false;
        }
    }

    private function getPasswordResetTemplate(string $employeeName, string $resetLink, int $expiryMinutes): string
    {
        return $this->renderEmailTemplate('password_reset', [
            'employee_name' => $employeeName,
            'reset_link' => $resetLink,
            'expiry_minutes' => $expiryMinutes,
        ]);
    }

    private function getNewUserTemplate(string $employeeName, string $userEmail, string $temporaryPassword, string $loginLink, int $expiryMinutes): string
    {
        return $this->renderEmailTemplate('new_user', [
            'employee_name' => $employeeName,
            'user_email' => $userEmail,
            'temporary_password' => $temporaryPassword,
            'login_link' => $loginLink,
            'expiry_minutes' => $expiryMinutes,
        ]);
    }

    /**
     * Send generic email
     */
    public function send(string $to, string $subject, string $htmlBody): bool
    {
        if (!$this->configured) {
            error_log('Mail service not configured');
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $htmlBody;
            $this->mailer->AltBody = strip_tags($htmlBody);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('Mail send error: ' . $e->getMessage());
            return false;
        }
    }
}