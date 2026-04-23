<?php

if (!function_exists('mailTemplateEscape')) {
	function mailTemplateEscape(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('mailTemplateRenderShell')) {
	function mailTemplateRenderShell(string $title, string $heading, string $bodyHtml, array $options = []): string
	{
		$brandName = mailTemplateEscape((string) ($options['brand_name'] ?? 'Expense Register Team'));
		$footerNote = mailTemplateEscape((string) ($options['footer_note'] ?? 'This is an automated email. Please do not reply to this message.'));
		$backgroundColor = (string) ($options['background_color'] ?? '#f6f8fb');
		$panelColor = (string) ($options['panel_color'] ?? '#ffffff');
		$headerColor = (string) ($options['header_color'] ?? '#1f3c5b');
		$accentColor = (string) ($options['accent_color'] ?? '#2563eb');
		$heading = mailTemplateEscape($heading);

		return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{$title}</title>
	<style>
		body { margin: 0; padding: 0; background: {$backgroundColor}; font-family: Arial, Helvetica, sans-serif; color: #243041; }
		.wrapper { width: 100%; padding: 24px 12px; box-sizing: border-box; }
		.container { max-width: 680px; margin: 0 auto; background: {$panelColor}; border-radius: 16px; overflow: hidden; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12); }
		.header { background: {$headerColor}; color: #fff; padding: 28px 32px; text-align: center; }
		.header h1 { margin: 0; font-size: 24px; line-height: 1.2; }
		.content { padding: 32px; line-height: 1.65; font-size: 15px; }
		.button { display: inline-block; margin: 16px 0 12px; padding: 14px 24px; border-radius: 10px; background: {$accentColor}; color: #fff !important; text-decoration: none; font-weight: 700; }
		.details { width: 100%; border-collapse: collapse; margin: 18px 0 20px; }
		.details td { padding: 10px 12px; border: 1px solid #dbe4f0; }
		.details td:first-child { font-weight: 700; background: #f8fafc; width: 32%; }
		.notice { margin: 18px 0; padding: 14px 16px; border-radius: 10px; background: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe; }
		.meta { margin: 18px 0; padding: 14px 16px; border-radius: 10px; background: #f8fafc; border: 1px solid #e2e8f0; }
		.warning { margin: 18px 0; padding: 14px 16px; border-radius: 10px; background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; font-weight: 700; }
		.credentials { margin: 18px 0; padding: 16px; border-radius: 12px; background: #f1f5f9; border: 1px solid #e2e8f0; }
		.credentials code, .link-box { word-break: break-all; }
		.credentials code { display: inline-block; margin-top: 6px; padding: 6px 10px; border-radius: 8px; background: #fff; border: 1px solid #cbd5e1; }
		.important { margin: 18px 0; padding: 14px 16px; border-radius: 10px; background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; font-weight: 700; }
		.steps { margin: 18px 0; padding: 16px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
		.steps ol { margin: 10px 0 0; padding-left: 20px; }
		.steps li { margin-bottom: 10px; }
		.link-box { background: #f1f5f9; padding: 14px 16px; border-radius: 10px; margin: 12px 0 20px; border: 1px solid #e2e8f0; }
		.footer { padding: 18px 32px 28px; text-align: center; color: #64748b; font-size: 12px; }
		@media only screen and (max-width: 640px) {
			.content, .header, .footer { padding-left: 20px; padding-right: 20px; }
			.header h1 { font-size: 22px; }
		}
	</style>
</head>
<body>
	<div class="wrapper">
		<div class="container">
			<div class="header">
				<h1>{$heading}</h1>
			</div>
			<div class="content">
				{$bodyHtml}
			</div>
			<div class="footer">{$footerNote}<br><strong>{$brandName}</strong></div>
		</div>
	</div>
</body>
</html>
HTML;
	}
}

if (!function_exists('renderEmailTemplate')) {
	function renderEmailTemplate(string $template, array $data = []): string
	{
		$template = strtolower(trim($template));

		if ($template === 'request_submission') {
			$employeeName = mailTemplateEscape((string) ($data['employee_name'] ?? 'User'));
			$requestTypeLabel = mailTemplateEscape((string) ($data['request_type_label'] ?? 'Request'));
			$requestNo = mailTemplateEscape((string) ($data['request_no'] ?? ''));
			$currency = mailTemplateEscape((string) ($data['currency'] ?? ''));
			$amount = mailTemplateEscape((string) ($data['amount'] ?? '0.00'));
			$budgetHead = mailTemplateEscape((string) ($data['budget_head'] ?? ''));
			$description = nl2br(mailTemplateEscape((string) ($data['description'] ?? '')));
			$requestLink = mailTemplateEscape((string) ($data['request_link'] ?? ''));
			$bodyHtml = '<p>Hello <strong>' . $employeeName . '</strong>,</p>'
				. '<p>Your ' . $requestTypeLabel . ' request has been submitted successfully and is now in the approval flow.</p>'
				. '<table class="details" role="presentation">'
				. '<tr><td>Request No</td><td>' . $requestNo . '</td></tr>'
				. '<tr><td>Amount</td><td>' . $currency . ' ' . $amount . '</td></tr>'
				. '<tr><td>Category</td><td>' . $budgetHead . '</td></tr>'
				. '<tr><td>Description</td><td>' . ($description !== '' ? $description : '—') . '</td></tr>'
				. '</table>'
				. '<div class="notice">You will be notified once there is an update on your request status.</div>'
				. '<p>You can track the progress anytime from your expense page:</p>'
				. '<p><a href="' . $requestLink . '" class="button">View Request</a></p>'
				. '<p>Or copy and paste this link into your browser:</p>'
				. '<div class="link-box">' . $requestLink . '</div>'
				. '<p>Regards,</p>';

			return mailTemplateRenderShell('Request Submitted Successfully', 'Request Submitted Successfully', $bodyHtml, [
				'header_color' => '#1f3c5b',
				'accent_color' => '#2563eb',
			]);
		}

		if ($template === 'request_action_required') {
			$approverName = mailTemplateEscape((string) ($data['approver_name'] ?? 'Approver'));
			$employeeName = mailTemplateEscape((string) ($data['employee_name'] ?? 'Employee'));
			$previousActor = mailTemplateEscape((string) ($data['previous_actor'] ?? 'Previous Approver'));
			$requestTypeLabel = mailTemplateEscape((string) ($data['request_type_label'] ?? 'Request'));
			$requestNo = mailTemplateEscape((string) ($data['request_no'] ?? ''));
			$currency = mailTemplateEscape((string) ($data['currency'] ?? ''));
			$amount = mailTemplateEscape((string) ($data['amount'] ?? '0.00'));
			$budgetHead = mailTemplateEscape((string) ($data['budget_head'] ?? ''));
			$description = nl2br(mailTemplateEscape((string) ($data['description'] ?? '')));
			$requestLink = mailTemplateEscape((string) ($data['request_link'] ?? ''));
			$approvalTimeout = (int) ($data['approval_timeout'] ?? 24);
			$isNewSubmission = (bool) ($data['is_new_submission'] ?? false);

			$transitionLine = $isNewSubmission
				? '<p>This request has been submitted by <strong>' . $employeeName . '</strong>.</p>'
				: '<p>This request has been reviewed by <strong>' . $previousActor . '</strong> and is now assigned to you for the next step.</p>';

			$bodyHtml = '<p>Hello <strong>' . $approverName . '</strong>,</p>'
				. '<p>A ' . $requestTypeLabel . ' request requires your review and action.</p>'
				. $transitionLine
				. '<table class="details" role="presentation">'
				. '<tr><td>Request No</td><td>' . $requestNo . '</td></tr>'
				. '<tr><td>Amount</td><td>' . $currency . ' ' . $amount . '</td></tr>'
				. '<tr><td>Category</td><td>' . $budgetHead . '</td></tr>'
				. '<tr><td>Description</td><td>' . ($description !== '' ? $description : '—') . '</td></tr>'
				. '</table>'
				. '<div class="meta">Please review and take action within <strong>' . $approvalTimeout . ' hours</strong>.</div>'
				. '<div class="warning">If no action is taken within this timeframe, the request will be automatically approved as per the system policy.</div>'
				. '<p>To proceed, please access the request using the link below:</p>'
				. '<p><a href="' . $requestLink . '" class="button">Review Request</a></p>'
				. '<p>Or copy and paste this link into your browser:</p>'
				. '<div class="link-box">' . $requestLink . '</div>'
				. '<p>Regards,</p>';

			return mailTemplateRenderShell(
				'Action Required: ' . $requestTypeLabel . ' #' . $requestNo . ' Pending Your Review',
				'Action Required',
				$bodyHtml,
				[
					'header_color' => '#7c2d12',
					'accent_color' => '#b45309',
				]
			);
		}

		if ($template === 'request_approved') {
			$employeeName = mailTemplateEscape((string) ($data['employee_name'] ?? 'Employee'));
			$requestTypeLabel = mailTemplateEscape((string) ($data['request_type_label'] ?? 'Request'));
			$requestNo = mailTemplateEscape((string) ($data['request_no'] ?? ''));
			$actorName = mailTemplateEscape((string) ($data['actor_name'] ?? 'Approver'));
			$comments = trim((string) ($data['comments'] ?? ''));
			$commentsEscaped = mailTemplateEscape($comments);
			$currency = mailTemplateEscape((string) ($data['currency'] ?? ''));
			$amount = mailTemplateEscape((string) ($data['amount'] ?? '0.00'));
			$budgetHead = mailTemplateEscape((string) ($data['budget_head'] ?? ''));
			$description = nl2br(mailTemplateEscape((string) ($data['description'] ?? '')));
			$requestLink = mailTemplateEscape((string) ($data['request_link'] ?? ''));

			$commentsHtml = $commentsEscaped !== ''
				? '<tr><td>Comments</td><td>' . nl2br($commentsEscaped) . '</td></tr>'
				: '';

			$bodyHtml = '<p>Hello <strong>' . $employeeName . '</strong>,</p>'
				. '<p>Your ' . $requestTypeLabel . ' request #' . $requestNo . ' has been successfully approved.</p>'
				. '<div class="notice"><strong>Final Approved By:</strong> ' . $actorName . '</div>'
				. '<table class="details" role="presentation">'
				. '<tr><td>Request No</td><td>' . $requestNo . '</td></tr>'
				. '<tr><td>Amount</td><td>' . $currency . ' ' . $amount . '</td></tr>'
				. '<tr><td>Category</td><td>' . $budgetHead . '</td></tr>'
				. '<tr><td>Description</td><td>' . ($description !== '' ? $description : '—') . '</td></tr>'
				. $commentsHtml
				. '</table>'
				. '<p>The approval process is now complete.</p>'
				. '<p>You can view the full details here:</p>'
				. '<p><a href="' . $requestLink . '" class="button">View Request</a></p>'
				. '<p>Or copy and paste this link into your browser:</p>'
				. '<div class="link-box">' . $requestLink . '</div>'
				. '<p>Regards,</p>';

			return mailTemplateRenderShell(
				'Request Approved: ' . $requestTypeLabel . ' #' . $requestNo,
				'Request Approved',
				$bodyHtml,
				[
					'header_color' => '#14532d',
					'accent_color' => '#15803d',
				]
			);
		}

		if ($template === 'request_rejected') {
			$employeeName = mailTemplateEscape((string) ($data['employee_name'] ?? 'Employee'));
			$requestTypeLabel = mailTemplateEscape((string) ($data['request_type_label'] ?? 'Request'));
			$requestNo = mailTemplateEscape((string) ($data['request_no'] ?? ''));
			$actorName = mailTemplateEscape((string) ($data['actor_name'] ?? 'Approver'));
			$stepTitle = mailTemplateEscape((string) ($data['step_title'] ?? 'Current Step'));
			$comments = trim((string) ($data['comments'] ?? ''));
			$commentsEscaped = nl2br(mailTemplateEscape($comments));
			$requestLink = mailTemplateEscape((string) ($data['request_link'] ?? ''));

			$bodyHtml = '<p>Hello <strong>' . $employeeName . '</strong>,</p>'
				. '<p>Your ' . $requestTypeLabel . ' request #' . $requestNo . ' has been rejected.</p>'
				. '<table class="details" role="presentation">'
				. '<tr><td>Rejected By</td><td>' . $actorName . '</td></tr>'
				. '<tr><td>Rejection Stage</td><td>' . $stepTitle . '</td></tr>'
				. '<tr><td>Reason for Rejection</td><td>' . ($commentsEscaped !== '' ? $commentsEscaped : '—') . '</td></tr>'
				. '</table>'
				. '<div class="warning">You may review the feedback and can resubmit the request.</div>'
				. '<p>View your request here:</p>'
				. '<p><a href="' . $requestLink . '" class="button">View Request</a></p>'
				. '<p>Or copy and paste this link into your browser:</p>'
				. '<div class="link-box">' . $requestLink . '</div>'
				. '<p>Regards,</p>';

			return mailTemplateRenderShell(
				'Request Rejected: ' . $requestTypeLabel . ' #' . $requestNo,
				'Request Rejected',
				$bodyHtml,
				[
					'header_color' => '#7f1d1d',
					'accent_color' => '#b91c1c',
				]
			);
		}

		if ($template === 'ticket_reassignment') {
			$newApproverName = mailTemplateEscape((string) ($data['new_approver_name'] ?? 'Approver'));
			$previousActor = mailTemplateEscape((string) ($data['previous_actor'] ?? 'Department Head'));
			$requestNo = mailTemplateEscape((string) ($data['request_no'] ?? ''));
			$comments = trim((string) ($data['comments'] ?? ''));
			$commentsEscaped = nl2br(mailTemplateEscape($comments));
			$requestLink = mailTemplateEscape((string) ($data['request_link'] ?? ''));

			$bodyHtml = '<p>Hello <strong>' . $newApproverName . '</strong>,</p>'
				. '<p>The Department Head (<strong>' . $previousActor . '</strong>) has reassigned Request #' . $requestNo . ' to you for further review and necessary action.</p>'
				. '<table class="details" role="presentation">'
				. '<tr><td>Request No</td><td>' . $requestNo . '</td></tr>'
				. '<tr><td>Reason for Reassignment</td><td>' . ($commentsEscaped !== '' ? $commentsEscaped : '—') . '</td></tr>'
				. '</table>'
				. '<div class="notice">You are requested to review the request details and take the appropriate action at the earliest.</div>'
				. '<p>Please log in to the Expense Portal to proceed.</p>'
				. '<p><a href="' . $requestLink . '" class="button">Open Request</a></p>'
				. '<p>Or copy and paste this link into your browser:</p>'
				. '<div class="link-box">' . $requestLink . '</div>'
				. '<p>Regards,</p>';

			return mailTemplateRenderShell(
				'Action Required: Request #' . $requestNo . ' Assigned to You',
				'Action Required',
				$bodyHtml,
				[
					'header_color' => '#4c1d95',
					'accent_color' => '#6d28d9',
				]
			);
		}

		if ($template === 'budget_threshold_alert') {
			$departmentName = mailTemplateEscape((string) ($data['department_name'] ?? 'Department'));
			$budgetHead = mailTemplateEscape((string) ($data['budget_head'] ?? 'Budget'));
			$usagePercent = mailTemplateEscape((string) ($data['usage_percent'] ?? '0'));
			$currency = mailTemplateEscape((string) ($data['currency'] ?? ''));
			$totalLimit = mailTemplateEscape((string) ($data['total_limit'] ?? '0.00'));
			$usedAmount = mailTemplateEscape((string) ($data['used_amount'] ?? '0.00'));

			$bodyHtml = '<p>Dear Team,</p>'
				. '<p>This is to notify you that the budget utilization for the following category has reached a critical threshold:</p>'
				. '<table class="details" role="presentation">'
				. '<tr><td>Department</td><td>' . $departmentName . '</td></tr>'
				. '<tr><td>Budget Head</td><td>' . $budgetHead . '</td></tr>'
				. '<tr><td>Utilization</td><td>' . $usagePercent . '%</td></tr>'
				. '<tr><td>Total Allocated Budget</td><td>' . $currency . ' ' . $totalLimit . '</td></tr>'
				. '<tr><td>Amount Spent to Date</td><td>' . $currency . ' ' . $usedAmount . '</td></tr>'
				. '</table>'
				. '<div class="warning">As the utilization has exceeded 90%, further expense requests under this budget head may be restricted once the limit is fully consumed.</div>'
				. '<p>We recommend reviewing the current spending and reallocating the budget if necessary to avoid disruptions.</p>'
				. '<p>Regards,</p>';

			return mailTemplateRenderShell(
				'URGENT: Budget Utilization Alert – ' . $departmentName,
				'Budget Utilization Alert',
				$bodyHtml,
				[
					'header_color' => '#7c2d12',
					'accent_color' => '#dc2626',
				]
			);
		}

		if ($template === 'budget_update_notification') {
			$departmentHeadName = mailTemplateEscape((string) ($data['department_head_name'] ?? 'Department Head'));
			$departmentName = mailTemplateEscape((string) ($data['department_name'] ?? 'Department'));
			$budgetHead = mailTemplateEscape((string) ($data['budget_head'] ?? 'Budget'));
			$fiscalYear = mailTemplateEscape((string) ($data['fiscal_year'] ?? ''));
			$fiscalPeriod = mailTemplateEscape((string) ($data['fiscal_period'] ?? ''));
			$currency = mailTemplateEscape((string) ($data['currency'] ?? ''));
			$totalLimit = mailTemplateEscape((string) ($data['total_limit'] ?? '0.00'));
			$previousLimit = mailTemplateEscape((string) ($data['previous_limit'] ?? '0.00'));
			$differenceAmount = mailTemplateEscape((string) ($data['difference_amount'] ?? '0.00'));
			$effectiveDate = mailTemplateEscape((string) ($data['effective_date'] ?? ''));
			$actionType = mailTemplateEscape((string) ($data['action_type'] ?? 'updated'));

			$bodyHtml = '<p>Hello <strong>' . $departmentHeadName . '</strong>,</p>'
				. '<p>This is to inform you that the budget for your department has been <strong>' . $actionType . '</strong> by the Finance team.</p>'
				. '<table class="details" role="presentation">'
				. '<tr><td>Department</td><td>' . $departmentName . '</td></tr>'
				. '<tr><td>Budget Head</td><td>' . $budgetHead . '</td></tr>'
				. '<tr><td>Fiscal Year</td><td>' . $fiscalYear . '</td></tr>'
				. '<tr><td>Fiscal Period</td><td>' . $fiscalPeriod . '</td></tr>'
				. '<tr><td>Current Budget Allocation</td><td>' . $currency . ' ' . $totalLimit . '</td></tr>'
				. '<tr><td>Previous Budget Allocation</td><td>' . $currency . ' ' . $previousLimit . '</td></tr>'
				. '<tr><td>Budget Change Amount</td><td>' . $currency . ' ' . $differenceAmount . '</td></tr>'
				. '<tr><td>Effective Date</td><td>' . $effectiveDate . '</td></tr>'
				. '</table>'
				. '<p>Please review the budget details and plan your department expenses accordingly.</p>'
				. '<p>For any questions or clarifications, please contact the Finance team.</p>'
				. '<p>Regards,</p>';

			return mailTemplateRenderShell(
				'Budget ' . ucfirst($actionType) . ' Notification – ' . $departmentName . ' (FY ' . $fiscalYear . ')',
				'Budget ' . ucfirst($actionType) . ' Notification',
				$bodyHtml,
				[
					'header_color' => '#1f3c5b',
					'accent_color' => '#2563eb',
				]
			);
		}

		if ($template === 'password_reset') {
			$employeeName = mailTemplateEscape((string) ($data['employee_name'] ?? 'User'));
			$resetLink = mailTemplateEscape((string) ($data['reset_link'] ?? ''));
			$expiryMinutes = (int) ($data['expiry_minutes'] ?? 60);

			$bodyHtml = '<p>Hello <strong>' . $employeeName . '</strong>,</p>'
				. '<p>We received a request to reset the password for your Expense Portal account associated with this email address.</p>'
				. '<p>To set a new password, click the secure button below:</p>'
				. '<p><a href="' . $resetLink . '" class="button">Reset Your Password</a></p>'
				. '<p>Or copy and paste this link into your browser:</p>'
				. '<div class="link-box">' . $resetLink . '</div>'
				. '<div class="meta"><strong>Link Expiry:</strong> This link will expire in <strong>' . $expiryMinutes . ' minutes</strong>.</div>'
				. '<div class="warning">If you did not request a password reset, you can ignore this email and your account will remain unchanged.</div>'
				. '<p>For any concerns, please contact your system administrator immediately.</p>';

			return mailTemplateRenderShell('Password Reset', 'Password Reset Request', $bodyHtml, [
				'header_color' => '#1f3c5b',
				'accent_color' => '#2563eb',
			]);
		}

		if ($template === 'new_user') {
			$employeeName = mailTemplateEscape((string) ($data['employee_name'] ?? 'User'));
			$userEmail = mailTemplateEscape((string) ($data['user_email'] ?? ''));
			$temporaryPassword = mailTemplateEscape((string) ($data['temporary_password'] ?? ''));
			$loginLink = mailTemplateEscape((string) ($data['login_link'] ?? ''));
			$expiryMinutes = (int) ($data['expiry_minutes'] ?? 60);

			$bodyHtml = '<p>Hello <strong>' . $employeeName . '</strong>,</p>'
				. '<p>Your account for the Expense Portal has been successfully created by the HR team.</p>'
				. '<div class="credentials">'
				. '<strong>Your Login Credentials:</strong><br>'
				. '<strong>Email:</strong> <code>' . $userEmail . '</code><br>'
				. '<strong>Temporary Password:</strong> <code>' . $temporaryPassword . '</code>'
				. '</div>'
				. '<div class="important">For security reasons, you must change your password when you first log in.</div>'
				. '<div class="steps">'
				. '<p><strong>Quick Start:</strong></p>'
				. '<ol>'
				. '<li>Click the login button below to open the Expense Portal</li>'
				. '<li>Sign in with your email and temporary password</li>'
				. '<li>You will be prompted to set a new permanent password</li>'
				. '<li>Create a strong password and confirm it</li>'
				. '<li>Start using the Expense Portal</li>'
				. '</ol>'
				. '</div>'
				. '<p><a href="' . $loginLink . '" class="button">Go to Expense Portal Login</a></p>'
				. '<p>Or copy and paste this link into your browser:</p>'
				. '<div class="link-box">' . $loginLink . '</div>'
				. '<p><strong>Important:</strong> Your temporary password will work for <strong>' . $expiryMinutes . ' minutes</strong>. Please login and change your password immediately.</p>'
				. '<p>If you face any issues accessing your account, please contact your administrator.</p>';

			return mailTemplateRenderShell('Account Created', 'Welcome to Expense Portal!', $bodyHtml, [
				'header_color' => '#1f7a4d',
				'accent_color' => '#1f7a4d',
			]);
		}

		return '';
	}
}
