# Expense Register

Expense Register is a PHP-based expense management application built for XAMPP/Apache with a MySQL backend. It includes authentication, a dashboard, user management, department management, and budget category management.

## Features

- Login/logout authentication
- Dashboard home screen
- User management with list, search, filters, pagination, create, and edit flows
- Department management with list, search, pagination, create, and edit flows
- Budget category management with list, search, status filter, pagination, and action buttons
- Sidebar navigation with page-specific icons
- Responsive Bootstrap-based UI with shared layouts and reusable templates

## Requirements

- PHP 8.2 or later
- MySQL or MariaDB
- Apache web server
- XAMPP recommended for local development

## Project Structure

```text
expense_portal/
├── Project Structure/
│   ├── assets/
│   │   └── css/
│   ├── configs/
│   ├── controllers/
│   ├── models/
│   ├── views/
│   │   ├── module-1/
│   │   └── templates/
│   ├── authenticate.php
│   ├── index.php
│   ├── init.php
│   └── router.php
└── README.md
```

## Setup

1. Copy the repository into your XAMPP `htdocs` folder.
2. Start Apache and MySQL from XAMPP.
3. Open [Project Structure/configs/env.php](Project%20Structure/configs/env.php) and confirm the database settings:
	- Host: `127.0.0.1`
	- Port: `3307`
	- Database: `expense_register`
	- Username: `root`
	- Password: empty by default
4. Create the required database and tables in MySQL if they are not already present.
5. Open the app in your browser.

## Local URL

Use this URL in your browser:

`http://localhost/expense_portal/Project%20Structure/index.php?route=dashboard`

## Login

The login screen is available from the dashboard route. A demo credential shown in the UI is:

- Email: `admin@example.com`
- Password: `admin123`

## Routes

- `?route=dashboard` - Login page
- `?route=auth` - Login submit handler
- `?route=module-1` - Dashboard
- `?route=users` - User list
- `?route=users/create` - Create user
- `?route=users/edit&id=ID` - Edit user
- `?route=departments` - Department list
- `?route=departments/create` - Create department
- `?route=departments/edit&id=ID` - Edit department
- `?route=budget-categories` - Budget category list
- `?route=logout` - Logout

## Access Control

The application uses role-based access control for module pages.

- `admin` can access administrative areas
- `hr` can access user and department management
- `finance` can access budget category management

## Pages

### Login

The login page uses the shared header/footer layout and shows validation feedback for required fields.

### Dashboard

The dashboard is the landing page after login and uses the shared app shell with sidebar navigation.

### Users

The user list supports:

- Search
- Role filter
- Department filter
- Status filter
- Pagination
- Edit action

### Departments

The department module supports:

- Search
- Pagination
- Create department
- Edit department
- Department head display

### Budget Categories

The budget category module supports:

- Search by code or name
- Status filtering
- Pagination
- Edit/Delete action buttons in the list view

## Controllers

- [Project Structure/controllers/AuthController.php](Project%20Structure/controllers/AuthController.php)
- [Project Structure/controllers/UserController.php](Project%20Structure/controllers/UserController.php)
- [Project Structure/controllers/DepartmentController.php](Project%20Structure/controllers/DepartmentController.php)
- [Project Structure/controllers/BudgetCategoryController.php](Project%20Structure/controllers/BudgetCategoryController.php)

## Models

- [Project Structure/models/AuthModel.php](Project%20Structure/models/AuthModel.php)
- [Project Structure/models/UserModel.php](Project%20Structure/models/UserModel.php)
- [Project Structure/models/DepartmentModel.php](Project%20Structure/models/DepartmentModel.php)
- [Project Structure/models/BudgetCategoryModel.php](Project%20Structure/models/BudgetCategoryModel.php)

## Views

- [Project Structure/views/module-1/login.php](Project%20Structure/views/module-1/login.php)
- [Project Structure/views/module-1/dashboard.php](Project%20Structure/views/module-1/dashboard.php)
- [Project Structure/views/module-1/user_list.php](Project%20Structure/views/module-1/user_list.php)
- [Project Structure/views/module-1/user_create.php](Project%20Structure/views/module-1/user_create.php)
- [Project Structure/views/module-1/department_list.php](Project%20Structure/views/module-1/department_list.php)
- [Project Structure/views/module-1/department_creation.php](Project%20Structure/views/module-1/department_creation.php)
- [Project Structure/views/module-1/budget_category_list.php](Project%20Structure/views/module-1/budget_category_list.php)
- [Project Structure/views/templates/header.php](Project%20Structure/views/templates/header.php)
- [Project Structure/views/templates/navbar.php](Project%20Structure/views/templates/navbar.php)
- [Project Structure/views/templates/sidebar.php](Project%20Structure/views/templates/sidebar.php)
- [Project Structure/views/templates/footer.php](Project%20Structure/views/templates/footer.php)

## Styling

Shared styles live in [Project Structure/assets/css](Project%20Structure/assets/css). The app currently uses dedicated CSS files for login, dashboard, user list, user creation, department list, department creation, and budget category list pages.

## Notes

- The app is configured to use `?route=dashboard` as the login entry point.
- Legacy `system administrator` naming has been normalized to `admin` in the application logic.
- Logout redirects back to the login page through the dashboard route.

## Development

When you change PHP files, lint them with:

```bash
php -l path/to/file.php
```

For a full syntax check of the project, run:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```
