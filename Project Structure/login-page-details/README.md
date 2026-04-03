# TaskFlowPro Login Page Details

This folder centralizes all details of the login page and the authentication flow around it.

## What is inside this folder

1. `LOGIN_WORKFLOW.md`
   - End-to-end request flow for login, logout, lockout, forgot-password, reset-password, and forced password change.
2. `SESSION_SECURITY_DETAILS.md`
   - Session keys, CSRF behavior, password hashing, lockout policy, and security notes.
3. `RELATED_FILES_INDEX.md`
   - Exact project files involved in login-page behavior and surrounding auth flow.

## Scope covered

- Login page UI and validation behavior.
- Login POST handler and redirection behavior.
- Database validation and password verification logic.
- Session state set at login and used by protected routes.
- Failed-attempt lockout flow.
- Password recovery (forgot/reset) and forced change-password flow.

## Entry points in app runtime

- `index.php` bootstraps app and includes `init.php`, then `router.php`.
- `init.php` starts session and generates CSRF token.
- `router.php` routes requests and handles auth POST pipelines.
- `views/login.php` renders login form and user-facing messages.
