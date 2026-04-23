<?php
$authError = isset($authError) ? (string) $authError : '';
$authSuccess = isset($authSuccess) ? (string) $authSuccess : '';
$oldEmail = isset($oldEmail) ? (string) $oldEmail : '';
?>

<div class="container login-shell d-flex align-items-center justify-content-center py-4">
	<div class="login-card-wrap">
		<div class="card login-card">
			<div class="card-body p-4 p-md-5">
				<div class="text-center mb-4">
					<div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white login-icon">
						<span class="fs-4">❓</span>
					</div>
					<h1 class="h4 mt-3 mb-1 login-title">Forgot Your Password?</h1>
					<p class="text-secondary mb-0">We'll send you a reset link</p>
				</div>

				<?php if ($authError !== ''): ?>
					<div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($authError, ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>

				<?php if ($authSuccess !== ''): ?>
					<div class="alert alert-success" role="alert"><?php echo htmlspecialchars($authSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>

				<form method="post" action="<?php echo htmlspecialchars(buildCleanRouteUrl('forgot-password-submit'), ENT_QUOTES, 'UTF-8'); ?>" novalidate>
					<div class="mb-3">
						<label for="email" class="form-label">Email Address</label>
						<input
							type="email"
							class="form-control"
							id="email"
							name="email"
							placeholder="Enter your email address"
							value="<?php echo htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8'); ?>"
							required
						>
					</div>

					<button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
				</form>

				<div class="mt-4 text-center">
					<small class="text-muted">
						Remember your password? <a href="<?php echo htmlspecialchars(buildCleanRouteUrl('login'), ENT_QUOTES, 'UTF-8'); ?>">Back to Login</a>
					</small>
				</div>

				<div class="alert alert-info mt-3 small">
					<strong>Note:</strong> A password reset link will be sent to your email address if an account exists. Check your inbox and spam folder.
				</div>
			</div>
		</div>
	</div>
</div>