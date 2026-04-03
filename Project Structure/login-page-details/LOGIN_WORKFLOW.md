# Login And Auth Workflow

## 1. Bootstrap Sequence

1. Request enters `index.php`.
2. `init.php` runs:
   - Starts PHP session if not active.
   - Creates `$_SESSION['csrf_token']` if missing.
   - Defines `APP_BASE` and `APP_ROOT`.
   - Registers autoloader and loads PDO from `config/db.php`.
3. `router.php` receives control and resolves route from `$_GET['url']`.

## 2. Route Guarding Rules

In `router.php`:

- If user is not logged in (`$_SESSION['user_id']` not set), only these routes stay public:
  - `login`
  - `forgot-password`
  - `reset-password`
  - `api` (but API still checks auth separately)
- Otherwise route is forced to `login`.

Additional rule:

- If `$_SESSION['needs_password_change'] === 1`, user is redirected to `change-password` except for:
  - `change-password`
  - `logout`
  - `api`

## 3. Login Page Render Flow

File: `views/login.php`

Behavior:

1. Reads query params: `error`, `success`, `remaining_attempts`, `remaining`.
2. Maps them to user-friendly alert messages.
3. Also checks session lock state (`$_SESSION['login_limit']`) on direct page load.
4. Renders login form:
   - `POST` to `APP_BASE/login`
   - hidden `csrf_token`
   - `email` and `password` inputs
5. Client-side JS validates:
   - email format
   - password length >= 6
6. Includes link to `APP_BASE/forgot-password`.

## 4. Login POST Pipeline

Route block: `router.php` when route is `login` and method is `POST`.

Sequence:

1. Read lockout state from `$_SESSION['login_limit']`.
2. If still locked (`locked_until > time()`):
   - Redirect to `login?error=locked&remaining=<seconds>`.
3. If lock expired:
   - Reset `failed_attempts` and `locked_until` in session.
4. Read submitted fields:
   - `email`
   - `password`
   - `csrf_token`
5. CSRF check with `hash_equals(session_token, post_token)`.
   - On fail -> redirect `login?error=csrf`.
6. Instantiate `User` and call `User::login($email, $password)`.
7. If success:
   - `unset($_SESSION['login_limit'])`
   - write audit row via `User::logLogin($_SESSION['user_id'])`
   - redirect to `dashboard`
8. If failure:
   - increment `failed_attempts`
   - if attempts >= 4, set lock for 10 minutes
   - redirect with either:
     - `error=locked` (plus `remaining`), or
     - `error=invalid` (plus `remaining_attempts`)

Lockout constants in router:

- max attempts: `4`
- lock duration: `10 * 60` seconds

## 5. Credential Verification And Session Population

Method: `User::login` in `src/classes/User.php`

Query:

- `SELECT * FROM users WHERE user_email = ? AND user_status = 'Active'`

Password check:

- `password_verify($password, $user['user_password'])`

On success, sets:

- `$_SESSION['user_id']`
- `$_SESSION['user_role']`
- `$_SESSION['user_name']`
- `$_SESSION['needs_password_change']`

Return values:

- `true` for successful authentication
- `false` for invalid credentials or inactive user

## 6. Logout Flow

Route block: `router.php` route `logout`

1. Clears `$_SESSION` array.
2. Expires session cookie (if cookie sessions enabled).
3. Calls `session_destroy()`.
4. Redirects to `login`.

## 7. Forgot/Reset Password Flow

Forgot password (`forgot-password` POST):

1. Validate CSRF and email format.
2. `User::createPasswordResetRequest($email, $requestIp)`.
3. If payload created, email reset link through `MailHelper::sendPasswordResetLink(...)`.
4. Always redirect to `forgot-password?success=sent` to prevent account enumeration.

Reset password (`reset-password` POST):

1. Validate CSRF and selector/token inputs.
2. Validate password policy and confirmation.
3. `User::consumePasswordResetToken($selector, $token, $newPassword)`.
4. On success redirect to `login?success=password_reset`.

## 8. Forced Change Password Flow

If `needs_password_change` is 1 in session:

- Router redirects authenticated user to `change-password` before any main app page.
- `change-password` POST validates CSRF and password rules.
- `User::updatePassword(...)` sets new hash and clears `needs_password_change`.
- Session value `$_SESSION['needs_password_change']` is set to `0`.
