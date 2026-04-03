# Related Files Index (Login And Auth)

## Primary Login Files

- `views/login.php`
  - Login page UI, message mapping, login form, client-side validation.
- `router.php`
  - Login POST handler, lockout logic, CSRF checks, redirects.
- `src/classes/User.php`
  - Credential verification, session key assignment, login audit write.
- `init.php`
  - Session initialization and CSRF token generation.
- `index.php`
  - Application bootstrap entry point.

## Password Recovery / Credential Lifecycle Files

- `views/forgot-password.php`
  - Forgot-password form and UX validation.
- `views/reset-password.php`
  - Reset-token verification UX and new-password submit form.
- `views/change-password.php`
  - Forced password update page.
- `libraries/MailHelper.php`
  - Sends reset and user credential emails.

## Security / Middleware / Session Consumers

- `src/includes/auth_middleware.php`
  - Protects authenticated pages by redirecting to login if session missing.
- `src/includes/header.php`
  - Reads login-derived session values for UI and frontend config.
- `controllers/api.php`
  - API auth guard and CSRF header validation.

## Database Objects Used By Login/Auth

- `database/schema.sql`
  - `users` table:
    - `user_email`
    - `user_password`
    - `user_status`
    - `needs_password_change`
    - role and identity fields
  - `user_login_records` table:
    - login audit timestamp per user
  - `password_reset_tokens` table:
    - reset selector/token hash/expiry/used status

## Quick Trace Path

1. `index.php`
2. `init.php`
3. `router.php`
4. `views/login.php` (GET) or `User::login` in `src/classes/User.php` (POST)
