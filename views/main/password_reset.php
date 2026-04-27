<?php
$token = trim((string) ($_GET['token'] ?? ''));
$pageError = isset($error) ? (string) $error : '';
$oldPassword = isset($oldPassword) ? (string) $oldPassword : '';

$canResetPassword = $user !== null && $token !== '';
?>

<div class="container login-shell d-flex align-items-center justify-content-center py-4">
	<div class="login-card-wrap">
		<div class="card login-card">
			<div class="card-body p-4 p-md-5">
				<div class="text-center mb-4">
					<div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white login-icon">
						<span class="fs-4">🔐</span>
					</div>
					<h1 class="h4 mt-3 mb-1 login-title">Reset Your Password</h1>
					<p class="text-secondary mb-0">Create a new secure password</p>
				</div>

				<?php if ($pageError !== ''): ?>
					<div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>

				<?php if (!$canResetPassword): ?>
					<div class="alert alert-warning" role="alert">
						<p class="mb-0">This password reset link is invalid or has expired.</p>
						<p class="mb-0 mt-2"><a href="<?php echo htmlspecialchars(buildCleanRouteUrl('forgot-password'), ENT_QUOTES, 'UTF-8'); ?>" class="alert-link">Request a new password reset link</a></p>
					</div>
					<div class="mt-3">
						<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('login'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary w-100">Back to Login</a>
					</div>
				<?php else: ?>
					<form method="post" action="<?php echo htmlspecialchars(buildCleanRouteUrl('password-reset-submit'), ENT_QUOTES, 'UTF-8'); ?>" novalidate>
						<input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

						<div class="mb-3">
							<label for="new_password" class="form-label">New Password</label>
							<input 
								type="password" 
								class="form-control" 
								id="new_password" 
								name="new_password" 
								placeholder="Enter new password (min. 8 characters)" 
								required
								minlength="8"
							>
							<small class="form-text text-muted">Minimum 8 characters required.</small>
						</div>

						<div class="mb-3">
							<label for="confirm_password" class="form-label">Confirm Password</label>
							<input 
								type="password" 
								class="form-control" 
								id="confirm_password" 
								name="confirm_password" 
								placeholder="Confirm your new password" 
								required
								minlength="8"
							>
						</div>

						<button type="submit" class="btn btn-primary w-100">Reset Password</button>
					</form>

					<div class="mt-3 text-center">
						<small class="text-muted">
							Remember your password? <a href="<?php echo htmlspecialchars(buildCleanRouteUrl('login'), ENT_QUOTES, 'UTF-8'); ?>">Back to Login</a>
						</small>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<script>
	(function () {
		const form = document.querySelector('form');
		if (!form) return;

		const newPassword = document.getElementById('new_password');
		const confirmPassword = document.getElementById('confirm_password');

		form.addEventListener('submit', function (e) {
			if (newPassword.value !== confirmPassword.value) {
				e.preventDefault();
				alert('Passwords do not match!');
				return;
			}

			if (newPassword.value.length < 8) {
				e.preventDefault();
				alert('Password must be at least 8 characters long!');
				return;
			}
		});
	})();
</script>