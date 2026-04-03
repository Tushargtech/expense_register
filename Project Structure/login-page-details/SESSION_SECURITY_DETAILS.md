# Session And Security Details

## Session Keys Created/Used In Auth

### Core identity keys set on successful login

- `$_SESSION['user_id']`: authenticated user id
- `$_SESSION['user_role']`: role id used for authorization checks
- `$_SESSION['user_name']`: display name used in UI
- `$_SESSION['needs_password_change']`: gate for forced password update

### CSRF protection key

- `$_SESSION['csrf_token']`
- Generated in `init.php` with `bin2hex(random_bytes(32))`
- Included in auth forms and validated in `router.php`

### Login rate-limit key

- `$_SESSION['login_limit']`
- Structure:
  - `failed_attempts` (int)
  - `locked_until` (unix timestamp)

## Password Handling

- Password verification during login:
  - `password_verify($plain, $storedHash)`
- Password hashing for updates/resets:
  - `password_hash($password, PASSWORD_BCRYPT)`

No plain-text password is persisted to database.

## Lockout Policy

- Login failures tracked in session (not database).
- Threshold: 4 failed attempts.
- Lock window: 10 minutes.
- During lock window, login POST is blocked and redirected with remaining time.
- On successful login, lock state is cleared.

## Audit Trail

After successful login:

- `User::logLogin($userId)` inserts into `user_login_records` with `NOW()` timestamp.

## Public vs Protected Routing

Unauthenticated users can access:

- login
- forgot-password
- reset-password
- api route path exists, but API branch still returns 401 when session is missing

All other routes are redirected to login when session is absent.

## Password Reset Token Security

From `User` class behavior:

- Reset token is random and one-time.
- Stored hash is used in database (`sha256` of raw token).
- Expiry is enforced (TTL constant in class).
- Existing active tokens for that user are invalidated before new insert.
- Invalid/expired/used token does not expose account details in response.

## Frontend Validation vs Backend Validation

Frontend JS on login/forgot/reset pages only improves UX.

Authoritative checks are server-side in `router.php` and `User` methods:

- CSRF
- email validity
- password policy
- token validity
- role/session checks
