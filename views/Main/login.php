<?php

$pageTitle = 'Login - Expense Register';
$pageStyles = ['assets/css/app.css'];
$bodyClass = 'bg-light';
require ROOT_PATH . '/views/templates/app_layout.php';
renderAppLayoutStart([
	'pageTitle' => $pageTitle,
	'pageStyles' => $pageStyles,
	'bodyClass' => $bodyClass,
	'includeChrome' => false,
]);

$authError = isset($authError) ? (string) $authError : '';
$authSuccess = isset($authSuccess) ? (string) $authSuccess : '';
$oldEmail = isset($oldEmail) ? (string) $oldEmail : '';
$credentialHints = isset($credentialHints) && is_array($credentialHints) ? $credentialHints : [];
?>

<div class="container login-shell d-flex align-items-center justify-content-center py-4">
	<div class="login-card-wrap">
		<div class="card login-card">
			<div class="card-body p-4 p-md-5">
				<div class="text-center mb-4">
					<div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white login-icon">
						<span class="fs-4">$</span>
					</div>
					<h1 class="h4 mt-3 mb-1 login-title">Expense Register</h1>
					<p class="text-secondary mb-0">Sign in to continue</p>
				</div>

				<?php if ($authError !== ''): ?>
					<div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($authError, ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>

				<?php if ($authSuccess !== ''): ?>
					<div class="alert alert-success" role="alert"><?php echo htmlspecialchars($authSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>

				<form id="loginForm" method="post" action="<?php echo htmlspecialchars(buildCleanRouteUrl('auth'), ENT_QUOTES, 'UTF-8'); ?>" novalidate>
					<div class="mb-3">
						<label for="email" class="form-label">Username</label>
						<input
							type="email"
							class="form-control"
							id="email"
							name="email"
							placeholder="Enter username"
							value="<?php echo htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8'); ?>"
							required
						>
					</div>

					<div class="mb-3">
						<label for="password" class="form-label">Password</label>
						<input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
					</div>
					<div class="mb-3 text-end">
					<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('forgot-password'), ENT_QUOTES, 'UTF-8'); ?>" class="small text-decoration-none">Forgot password?</a>
				</div>

					<button type="submit" class="btn btn-primary w-100">Sign In</button>
				</form>
			</div>
		</div>
	</div>
</div>

<script>
	(function () {
		const form = document.getElementById('loginForm');
		if (!form) return;

		form.addEventListener('submit', function (event) {
			if (!form.checkValidity()) {
				event.preventDefault();
				event.stopPropagation();
			}
			form.classList.add('was-validated');
		});
	})();
</script>

<?php renderAppLayoutEnd(); ?>
