# Expense Register

Expense Register is a PHP + MySQL application for managing users, departments, budget categories, and department budgets from uploaded files.

## Current Features

- Login/logout authentication
- Dashboard with role-based navigation
- User management: list, filter, pagination, create, edit
- Department management: list, create, edit
- Budget category management: list, filter, create, edit
- Budget uploader for Finance/Admin:
	- CSV upload
	- Excel upload (`.xlsx`, `.xls`)
	- Image upload (`.jpg`, `.jpeg`, `.png`) via OCR
- Upload preview table showing row-by-row parsed values
- Department name shown in preview
- Budget category ID shown in preview
- File-level atomic save for uploader:
	- If any row has errors, no rows are inserted
	- Insert starts only when full file validates

## Requirements

- PHP 8.2+ (current dependency set is compatible with PHP >= 8.1)
- MySQL or MariaDB
- Apache (XAMPP recommended)
- Composer
- Tesseract OCR (for image uploads)

## Project Structure

```text
expense_portal/
├── assets/
│   ├── css/
│   └── js/
├── configs/
├── controllers/
├── libraries/
├── models/
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

1. Place project in XAMPP `htdocs`.
2. Start Apache and MySQL.
3. Configure database in [configs/env.php](configs/env.php):
	 - host: `127.0.0.1`
	 - port: `3307`
	 - database: `expense_register`
	 - username: `root`
	 - password: empty by default
4. Run Composer install in project root:

```bash
composer install
```

5. Install Tesseract (needed for image upload parsing).
6. Open app in browser:

```text
http://localhost/expense_portal/index.php?route=dashboard
```

## Routes

- `?route=dashboard` - Login page
- `?route=auth` - Login submit
- `?route=home` - Dashboard
- `?route=users` - User list
- `?route=users/create` - Create user (GET/POST)
- `?route=users/edit&id=ID` - Edit user (GET/POST)
- `?route=departments` - Department list
- `?route=departments/create` - Create department (GET/POST)
- `?route=departments/edit&id=ID` - Edit department (GET/POST)
- `?route=budget-categories` - Budget category list
- `?route=budget-categories/create` - Create budget category (GET/POST)
- `?route=budget-categories/edit&id=ID` - Edit budget category (GET/POST)
- `?route=budget-uploader` - Budget uploader (GET/POST)
- `?route=logout` - Logout

## Access Control

- `admin`: full access
- `hr`: users and departments
- `finance`: budget categories and budget uploader

## Budget Uploader (Important)

### Supported file types

- CSV
- XLSX / XLS
- JPG / JPEG / PNG

### Validation and Insert Behavior

- Upload is validated row by row.
- Data is not inserted while validation is still in progress.
- If one or more rows fail validation, no row is inserted from that file.
- If all rows pass, all rows are inserted in a transaction.

### What preview shows

- Row number
- Department name
- Fiscal year
- Fiscal period
- Category
- Category ID
- Amount
- Currency
- Status and row issues

## Test Upload Templates (README-only)

Sample test content is documented here so test files do not need to be committed to GitHub/Bitbucket.

### CSV Template

Create a local `.csv` file with this content:

```csv
Department,Budget_Fiscal_Year,Budget_Fiscal_Period,Budget_Category,Budget_Allocated_Amount,Budget_Currency,Budget_Notes
Finance,2026,Qt,Operations,150000,INR,Quarter 1 operating budget
HR,2026,Q2,Recruitment,80000,INR,Hiring drive allocation
IT,2026,Q3,Infrastructure,220000,INR,Server and cloud costs
Sales,2026,Q4,Travel,60000,INR,Client visit travel budget
```

### Excel Template

Create an `.xlsx` file with the same headers as the CSV template:

- `Department`
- `Budget_Fiscal_Year`
- `Budget_Fiscal_Period`
- `Budget_Category`
- `Budget_Allocated_Amount`
- `Budget_Currency`
- `Budget_Notes`

Add the same sample rows as above.

### Image OCR Template

Create an image containing key-value lines like this:

```text
Department: Finance
Budget Fiscal Year: 2026
Budget Fiscal Period: Qt
Budget Category: Operations
Budget Allocated Amount: 150000
Budget Currency: INR
Budget Notes: Quarter 1 operating budget
```

Notes:

- Keep text high contrast (dark text on white background) for better OCR.
- Use one field per line.

## Dependency Notes

Current spreadsheet dependencies are pinned for PHP 8.2 compatibility:

- `phpoffice/phpspreadsheet` `^2.2`
- `maennchen/zipstream-php` `^2.4`

If Composer dependencies are changed, run:

```bash
composer update
```

## Development

Lint one file:

```bash
php -l path/to/file.php
```

Lint all PHP files:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```
