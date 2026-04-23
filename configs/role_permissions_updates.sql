-- Role permission updates for Expense Register
-- Date: 13-Apr-2026
-- Notes:
-- 1) role_permissions expects JSON
-- 2) keys map to checks in libraries/RbacService.php
-- 3) manager and department_head are derived from user relationships

UPDATE roles
SET role_permissions = JSON_OBJECT(
  'users', JSON_OBJECT(
    'view', true,
    'list', true,
    'view_all', true,
    'manage', false
  ),
  'departments', JSON_OBJECT(
    'view', true,
    'list', true,
    'manage', true,
    'create', true,
    'edit', true
  ),
  'budget_categories', JSON_OBJECT(
    'view', true,
    'manage', false
  ),
  'budget_monitor', JSON_OBJECT(
    'view', false,
    'view_all', false
  ),
  'workflows', JSON_OBJECT(
    'list', true,
    'view', true,
    'create', true,
    'edit', true,
    'manage', false
  ),
  'expenses', JSON_OBJECT(
    'view', true,
    'review', false,
    'review_all', false
  )
)
WHERE role_slug = 'admin';

UPDATE roles
SET role_permissions = JSON_OBJECT(
  'users', JSON_OBJECT(
    'view', true,
    'list', true,
    'view_all', true,
    'manage', false
  ),
  'departments', JSON_OBJECT(
    'view', true,
    'list', true,
    'manage', false,
    'create', false,
    'edit', false
  ),
  'budget_categories', JSON_OBJECT(
    'view', true,
    'manage', true
  ),
  'budget_monitor', JSON_OBJECT(
    'view', true,
    'view_all', true
  ),
  'workflows', JSON_OBJECT(
    'list', true,
    'view', true,
    'create', true,
    'edit', true,
    'manage', false
  ),
  'expenses', JSON_OBJECT(
    'view', true,
    'review', true,
    'review_all', true
  )
)
WHERE role_slug = 'finance';

UPDATE roles
SET role_permissions = JSON_OBJECT(
  'users', JSON_OBJECT(
    'view', true,
    'list', true,
    'view_all', true,
    'manage', true
  ),
  'departments', JSON_OBJECT(
    'view', true,
    'list', true,
    'manage', false,
    'create', false,
    'edit', false
  ),
  'budget_categories', JSON_OBJECT(
    'view', false,
    'manage', false
  ),
  'budget_monitor', JSON_OBJECT(
    'view', false,
    'view_all', false
  ),
  'workflows', JSON_OBJECT(
    'list', true,
    'view', true,
    'create', false,
    'edit', false,
    'manage', false
  ),
  'expenses', JSON_OBJECT(
    'view', true,
    'review', false,
    'review_all', false
  )
)
WHERE role_slug = 'hr';

UPDATE roles
SET role_permissions = JSON_OBJECT(
  'users', JSON_OBJECT(
    'view', true,
    'list', true,
    'view_all', false,
    'manage', false
  ),
  'departments', JSON_OBJECT(
    'view', true,
    'list', true,
    'manage', false,
    'create', false,
    'edit', false
  ),
  'budget_categories', JSON_OBJECT(
    'view', false,
    'manage', false
  ),
  'budget_monitor', JSON_OBJECT(
    'view', false,
    'view_all', false
  ),
  'workflows', JSON_OBJECT(
    'list', false,
    'view', true,
    'create', false,
    'edit', false,
    'manage', false
  ),
  'expenses', JSON_OBJECT(
    'view', true,
    'review', false,
    'review_all', false
  )
)
WHERE role_slug = 'employee';

UPDATE users
SET user_role = CASE
  WHEN user_role IN ('manager', 'department_head', 'dept_head', 'depthead') THEN 'employee'
  WHEN user_role IN ('hr_manager', 'hr_department_head', 'hr_dept_head') THEN 'hr'
  ELSE user_role
END;

DELETE FROM roles
WHERE role_slug IN ('manager', 'department_head', 'hr_manager', 'hr_department_head', 'hr_dept_head', 'dept_head', 'depthead');

-- Verify
SELECT role_id, role_name, role_slug, role_permissions
FROM roles
ORDER BY role_id;
