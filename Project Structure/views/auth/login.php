<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($title ?? 'Expense Register - Login', ENT_QUOTES, 'UTF-8'); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/app.css" rel="stylesheet">
  <link href="assets/css/login.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container login-shell d-flex align-items-center justify-content-center py-4">
    <div class="login-card-wrap">
      <div class="card login-card">
      <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white login-icon">
            <i class="bi bi-wallet2 fs-4"></i>
          </div>
          <h1 class="h4 mt-3 mb-1 login-title">Expense Register</h1>
          <p class="text-secondary mb-0">Sign in to continue</p>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger py-2" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
          <div class="alert alert-success py-2" role="alert"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="?route=login" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars((string) $oldEmail, ENT_QUOTES, 'UTF-8'); ?>" placeholder="name@example.com" required>
            </div>
            <div class="invalid-feedback d-block" id="emailError"></div>
          </div>

          <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock"></i></span>
              <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
            </div>
            <div class="invalid-feedback d-block" id="passwordError"></div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="rememberMe" disabled>
              <label class="form-check-label" for="rememberMe">Remember me</label>
            </div>
            <a href="#" id="forgotPassword" class="link-primary text-decoration-none">Forgot password?</a>
          </div>

          <button type="submit" id="signInBtn" class="btn btn-primary w-100">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
          </button>
        </form>
      </div>
    </div>
    </div>
  </div>

  <script>
    const form = document.getElementById('loginForm');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const emailError = document.getElementById('emailError');
    const passwordError = document.getElementById('passwordError');

    function validate() {
      let ok = true;
      emailError.textContent = '';
      passwordError.textContent = '';
      email.classList.remove('is-invalid');
      password.classList.remove('is-invalid');

      const emailValue = email.value.trim();
      const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      if (!emailValue) {
        email.classList.add('is-invalid');
        emailError.textContent = 'Email is required.';
        ok = false;
      } else if (!emailPattern.test(emailValue)) {
        email.classList.add('is-invalid');
        emailError.textContent = 'Enter a valid email address.';
        ok = false;
      }

      if (!password.value) {
        password.classList.add('is-invalid');
        passwordError.textContent = 'Password is required.';
        ok = false;
      }

      return ok;
    }

    form.addEventListener('submit', function (e) {
      if (!validate()) {
        e.preventDefault();
      }
    });

    document.getElementById('forgotPassword').addEventListener('click', function (e) {
      e.preventDefault();
      alert('Forgot password flow will be added next.');
    });
  </script>
</body>
</html>
