# Expense Register

Expense Register is a PHP 8.2 + MySQL expense workflow application with role-based access control, budget management, and attachment handling stored in database records.

## Overview

The application provides:

- Authentication, session management, and protected routes
- Role-aware dashboard and navigation
- User and department administration
- Expense request creation, listing, and review
- Attachment view/download directly from database payloads
- Budget category management
- Budget upload from CSV, Excel, and OCR-supported images
- Budget monitor views with department scope enforcement
- Workflow creation/editing with multi-step approval rules
- Centralized flash-message system for redirects and status feedback

## Tech Stack

- PHP 8.2+
- MariaDB / MySQL
- Apache (XAMPP recommended)
- Composer dependencies:
  - phpoffice/phpspreadsheet
  - maennchen/zipstream-php
- Tesseract OCR for image-based budget parsing

## Current Project Layout

```text
expense_portal/
├── assets/
│   ├── css/
│   └── js/
├── configs/
│   ├── db.php
│   ├── env.php
│   └── schema.sql
├── controllers/
│   ├── AuthController.php
│   ├── UserController.php
│   ├── DepartmentController.php
│   ├── BudgetCategoryController.php
│   ├── BudgetController.php
│   ├── BudgetMonitorController.php
│   ├── ExpenseController.php
│   └── WorkflowController.php
├── libraries/
│   ├── BudgetFileParser.php
│   ├── FlashMessage.php
│   └── RbacService.php
├── models/
│   ├── AuthModel.php
│   ├── UserModel.php
│   ├── DepartmentModel.php
│   ├── BudgetCategoryModel.php
│   ├── BudgetModel.php
│   ├── BudgetMonitorModel.php
│   ├── ExpenseModel.php
│   └── WorkflowModel.php
├── views/
│   ├── module-1/
│   └── templates/
├── vendor/
├── authenticate.php
├── composer.json
├── composer.lock
├── index.php
├── init.php
├── router.php
└── README.md
```

## Setup

1. Place the project in your htdocs directory.
2. Start Apache and MySQL from XAMPP.
3. Import schema from configs/schema.sql.
4. Confirm environment config in configs/env.php.
5. Install dependencies:

```bash
composer install
```

6. Install Tesseract OCR if image upload parsing is required.
7. Open the app:

```text
http://localhost/expense_portal/index.php?route=dashboard
```

## Configuration

Default environment settings are defined in configs/env.php:

- host: 127.0.0.1
- port: 3307
- database: expense_register
- username: root
- password: empty by default

Adjust these values per your local setup.

## Route Map

Authentication and shell:

- ?route=dashboard
- ?route=auth
- ?route=home
- ?route=logout
- ?route=forbidden

Users and departments:

- ?route=users
- ?route=users/create
- ?route=users/edit&id=ID
- ?route=departments
- ?route=departments/create
- ?route=departments/edit&id=ID

Budget:

- ?route=budget-categories
- ?route=budget-categories/create
- ?route=budget-categories/edit&id=ID
- ?route=budget-uploader
- ?route=budget-monitor

Expenses:

- ?route=expenses
- ?route=expenses/create
- ?route=expenses/review&id=ID
- ?route=expenses/attachment/view&request_id=ID&attachment_id=ID
- ?route=expenses/attachment/download&request_id=ID&attachment_id=ID

Workflows:

- ?route=workflows
- ?route=workflows/create
- ?route=workflows/edit&id=ID

## RBAC Policy (Current)

### Admin

- Can view dashboard
- Can view all users
- Cannot create or edit users
- Can view, create, and edit departments
- Can view and create expenses
- Can view budget categories
- Cannot create or edit budget categories
- Cannot access budget upload
- Cannot access budget monitor
- Can view, create, and edit workflows

### Finance

- Can manage budget categories
- Can access budget upload
- Can create/edit workflows
- Can access expense requests

### HR and HR-Scoped Leads

- HR can manage users and departments
- HR can access expense requests

### Manager and Department Head

- Department-scoped user visibility
- Expense access based on role scope
- Budget monitor is restricted to manager/dept_head in Admin department only
- Budget monitor data is restricted to own department scope

### Employee

- Department-scoped user visibility
- Own-scope expense access

## Budget Upload Behavior

Supported formats:

- CSV
- XLSX / XLS
- JPG / JPEG / PNG via OCR

Validation and persistence behavior:

- File rows are parsed and validated before insert commit
- If any row is invalid, no row is inserted
- If all rows are valid, rows are saved in one transaction
- Upload preview is stored in session and rendered post-redirect

## Expense and Attachment Behavior

- Expense creation requires valid category and workflow mapping
- Attachments are validated by extension, MIME, and size
- Attachment content is stored as base64 payload in request_attachments
- View and download endpoints stream attachment binary directly from database

## Workflow Behavior

- Workflow list supports search and filtering
- Create and edit support multi-step approval definitions
- Step approver modes: role, user, department_head
- Amount ranges and required/timeout controls are enforced at validation level

## Flash Message System

Shared flash messaging is implemented through libraries/FlashMessage.php.

- flash_success(message)
- flash_error(message)
- flash_consume() in the template layer

The shared template views/templates/flash_message.php renders redirect feedback consistently for all modules.

## Development Commands

Lint one file:

```bash
php -l path/to/file.php
```

Lint all non-vendor PHP files:

```bash
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n 1 php -l
```

## JSON API

The application now exposes a parallel JSON API under `?route=api/v1/...` while keeping the existing browser routes intact.

### Status and auth

- `GET ?route=api/v1/health`
- `POST ?route=api/v1/auth/login`
- `POST ?route=api/v1/auth/logout`
- `GET ?route=api/v1/auth/me`

### Core resources

- `GET ?route=api/v1/users`
- `GET ?route=api/v1/users&id=ID`
- `POST ?route=api/v1/users`
- `PUT ?route=api/v1/users&id=ID`

- `GET ?route=api/v1/departments`
- `GET ?route=api/v1/departments&id=ID`
- `POST ?route=api/v1/departments`
- `PUT ?route=api/v1/departments&id=ID`

- `GET ?route=api/v1/budget-categories`
- `GET ?route=api/v1/budget-categories&id=ID`
- `POST ?route=api/v1/budget-categories`
- `PUT ?route=api/v1/budget-categories&id=ID`

- `GET ?route=api/v1/budget-monitor`
- `GET ?route=api/v1/budgets/upload`
- `POST ?route=api/v1/budgets/upload`

- `GET ?route=api/v1/expenses`
- `GET ?route=api/v1/expenses&id=ID`
- `GET ?route=api/v1/expenses/review&id=ID`
- `POST ?route=api/v1/expenses`
- `GET ?route=api/v1/expenses/attachment/view&request_id=ID&attachment_id=ID`
- `GET ?route=api/v1/expenses/attachment/download&request_id=ID&attachment_id=ID`

- `GET ?route=api/v1/workflows`
- `GET ?route=api/v1/workflows&id=ID`
- `POST ?route=api/v1/workflows`
- `PUT ?route=api/v1/workflows&id=ID`

### Response shape

- Success: `{ "success": true, "data": ..., "meta": ... }`
- Error: `{ "success": false, "message": "...", "errors": {...} }`

### Notes

- The API uses the same session and RBAC rules as the existing UI.
- Binary attachment download/view routes remain binary responses because they deliver file payloads, not JSON.
- File upload routes accept multipart form-data.
- Workflow create/update payloads must include `budget_category_id` so each workflow is mapped to a specific budget category.

## Data Model Notes

- Role source of truth is roles.role_slug
- users.user_role stores role slug and is FK-linked to roles
- Keep role seeds and RBAC method logic aligned whenever adding/changing roles

## Maintenance Notes

- Avoid adding route-specific flash key patterns; use shared flash helper only
- Keep controller authorization checks delegated to RbacService
- Re-run lint and key route checks after RBAC changes
