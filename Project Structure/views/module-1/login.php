<?php

$pageTitle = 'Login - Expense Register';
$pageStyles = ['assets/css/login.css'];
$bodyClass = 'bg-light';
require ROOT_PATH . '/views/templates/header.php';

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

				<form id="loginForm" method="post" action="?route=auth" novalidate>
					<div class="mb-3">
						<label for="email" class="form-label">Email</label>
						<input
							type="email"
							class="form-control"
							id="email"
							name="email"
							placeholder="name@example.com"
							value="<?php echo htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8'); ?>"
							required
						>
					</div>

					<div class="mb-3">
						<label for="password" class="form-label">Password</label>
						<input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
					</div>

					<button type="submit" class="btn btn-primary w-100">Sign In</button>
				</form>

				<p class="small text-muted mt-3 mb-0">
					Demo fallback credentials: <strong>admin@example.com / admin123</strong>
				</p>
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

<?php require ROOT_PATH . '/views/templates/footer.php'; ?>
