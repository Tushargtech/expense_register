<?php

$pageTitle = 'Login - Expense Register';
$pageStyles = ['assets/css/login.css'];
$bodyClass = 'bg-light';
require ROOT_PATH . '/views/templates/header.php';

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

				<form id="loginForm" method="post" action="?route=auth" novalidate>
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

					<button type="submit" class="btn btn-primary w-100">Sign In</button>
				</form>


				<?php if (!empty($credentialHints)): ?>
					<div class="mt-4">
						<h2 class="h6 mb-2">Test Credentials For RBAC Validation</h2>
						<div class="table-responsive">
							<table class="table table-sm table-bordered align-middle mb-0">
								<thead class="table-light">
									<tr>
										<th>Name</th>
										<th>Role</th>
										<th>Email</th>
										<th>Password</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($credentialHints as $hint): ?>
										<tr>
											<td><?php echo htmlspecialchars((string) ($hint['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars((string) ($hint['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars((string) ($hint['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars((string) ($hint['password_hint'] ?? 'Use assigned password'), ENT_QUOTES, 'UTF-8'); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<p class="small text-muted mt-2 mb-0">Password hint shows <strong>Welcome@123</strong> only for users currently using the default generated password.</p>
					</div>
				<?php endif; ?>
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
