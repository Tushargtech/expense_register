# Functional Specification Document (FSD)

## Document Control

| Field | Value |
|-------|-------|
| **Project Name** | Expense Register Management System |
| **Module Name** | Budget Management & Financial Requests |
| **Prepared By** | Development Team |
| **Reviewed By** | QA & Product Team |
| **Date** | 14 April 2026 |
| **Status** | Active |

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 14-Apr-2026 | Dev Team | Initial document with budget edit/delete, API endpoints, and permission framework |
| | | | • Budget Edit/Delete workflow (web) |
| | | | • Budget API endpoints (read, update, delete) |
| | | | • Finance-scoped permission model |
| | | | • DB-driven option lookups |
| 1.1 | 14-Apr-2026 | Dev Team | Route and auth flow alignment update |
| | | | • Web entry route changed to `/login` |
| | | | • Login form action documented as `/auth` |
| | | | • Authenticated landing route documented as `/dashboard` |
| | | | • Auth API request/response schema aligned to implementation |

---

## Overview

### Purpose
This FSD defines the functional requirements, user interfaces, API specifications, and technical implementation details for the Expense Register Management System. The system manages expense requests, budgets, workflows, and financial approvals with role-based access control and department-scoped visibility.

### Scope

**Included:**
- Authentication & Session Management (Login, Logout, Current User)
- Budget Management (upload, view, edit, delete)
- Financial Request Management (expenses)
- Expense Review & Approval Workflows
- Department & User Management
- Role-Based Access Control (RBAC)
- Audit Trail Logging
- RESTful JSON APIs
- Multi-currency Support

**Excluded:**
- Email notifications (defined but not fully implemented)
- Advanced analytics/reporting
- Third-party payment integration
- Mobile-native applications

---

## Definitions

| Term | Description |
|------|-------------|
| **Expense Request (Request)** | Reimbursement request by an employee for an expense incurred; includes attachments and workflow approval steps |
| **Budget** | Allocated financial resource for a department/fiscal year/period/category; manually managed via upload or edit interface |
| **Budget Category** | Classification of budgets (e.g., travel, supplies, equipment); has type and is_active flag |
| **Workflow** | Approval routing configuration defining who approves requests based on type and amount; tied to budget categories |
| **Department** | Organizational unit with associated budgets, users, and managers |
| **Finance Role/Dept** | Special organization-wide role (finance user) or finance department; grants full budget management and monitoring access |
| **RBAC** | Role-Based Access Control; users assigned roles (employee, manager, dept_head, finance, hr) with canonical permission keys |
| **Clean URLs** | Pretty route format (e.g., `/budget-monitor`) instead of query-string fallback (`?route=budget-monitor`) |
| **Monitor** | Budget Monitor page showing aggregated budget vs. spent amounts, filtered by department/fiscal-year/category |
| **Uploader** | Budget CSV/Excel file upload page with row-level validation and preview before DB commit |

---

## References

### BRD
- Expense Register Business Requirements Document (external)

### Technical Docs
- API Architecture Guide
- RBAC Permission Keys Reference (`configs/role_permissions_updates.sql`)
- Database Schema (`configs/schema.sql`)

---

## Business Requirements

### Problem Statement
Organizations need centralized expense management with budget tracking, multi-level approval workflows, and financial oversight. Finance teams require the ability to manage budgets manually or via file upload, monitor allocations by department, and ensure policy compliance.

### Business Objectives
1. Provide secure, role-based access to financial data
2. Enable flexible budget management (upload and manual edit/delete)
3. Support multi-approval workflows for expense requests
4. Maintain audit trails for compliance
5. Support department-scoped visibility for managers
6. Enable organization-wide views for finance users

### Stakeholders

| Role | Name | Responsibility |
|------|------|-----------------|
| Finance Manager | [TBD] | Budget management, monitoring, approval oversight |
| Department Manager | [TBD] | Department budget visibility, expense approvals |
| Employee | [TBD] | Submit expense requests, view own submissions |
| HR Admin | [TBD] | User and department management |
| IT Admin | [TBD] | System configuration and maintenance |

---

## User Interface / Screen Details

### Screen 0: Login

**Screen Name:** Login  
**Description:** Public entry page for credential-based authentication.

**Navigation Path:** `/login`  
**Form Action:** `/auth` (POST)

**User Roles Allowed:**
- Public (unauthenticated users)

**Device/Platform:** Web  
**Responsive Behaviour:** Centered card layout on desktop and mobile

---

#### Actions

| Action | Description | Trigger | Pre-condition | Post-condition | API/Service Called | Success Message | Error Handling |
|--------|-------------|---------|---------------|-----------------|-------------------|-----------------|-----------------|
| Login | Authenticate and create session | Click "Login" button | Valid email and password | Redirect to `/dashboard` | `AuthController::login()` via `/auth` | "Login successful." | Stay on `/login` with flash error |

---

### Screen 1: Budget Monitor

**Screen Name:** Budget Monitor (Dashboard View)  
**Description:** Read-only aggregated view of all budgets by department, category, and fiscal year. Shows allocated vs. spent amounts, utilization percentages. Finance users see all departments; non-finance users see only their own department.

**Navigation Path:** `/budget-monitor` or `?route=budget-monitor`

**User Roles Allowed:**
- finance (organization-wide)
- employee, manager, dept_head (in finance department only)
- Anyone with budget_monitor.view permission

**Device/Platform:** Web  
**Responsive Behaviour:** Responsive table layout; filters and KPI cards stack on mobile

---

#### UI Elements

| Field Name | Label | Type | Control Type | Mandatory | Read Only | Default | Placeholder | Validation |
|-----------|-------|------|-------------|-----------|-----------|---------|-------------|-----------|
| fiscal_year | Fiscal Year | Select Dropdown | Dropdown | No | No | All | – | Retrieved from DB budgets |
| budget_type | Budget Type | Select Dropdown | Dropdown | No | No | All | – | DB-driven category types |
| department_id | Department | Select Dropdown | Dropdown | No | No | All (if finance) | – | All available departments |
| category_id | Budget Category | Select Dropdown | Dropdown | No | No | All | – | DB-driven categories |
| view_button | View Results | Button | Submit Button | No | N/A | – | – | POST form submission |
| reset_button | Reset Filters | Button | Link Button | No | N/A | – | – | Clears filters, reloads page |

**Max Length:** Standard select dropdowns (no text input)  
**Format:** Date format for fiscal_year (YYYY or YYYY-Q#)  
**Tooltip:** "Filter budgets by year, type, department, and category. Non-finance users see only their department."  
**Remarks:** Department filter hidden for non-finance users

---

#### Layout & Sections

**Header:**
- Page title: "Budget Monitor"
- Subtitle role label: "Budget Management" (finance) or scoped note for non-finance

**Footer:**
- Standard site footer with links

**Sections/Panels:**
1. **KPI Cards** (4 cards):
   - Total Allocated
   - Total Spent
   - Remaining
   - Utilization %

2. **Filter Card**: Dropdown filters (fiscal_year, type, department, category) + View & Reset buttons

3. **Department Wise Budget Table**:
   - Columns: Department, Budgets (count), Allocated, Spent, Remaining, Utilization
   - Sortable by department name
   - Empty state: "No department budgets found for the selected scope."

4. **Budget Types Panel** (side card):
   - List of budget types with counts and allocated/spent amounts

5. **Category Wise Budget Table**:
   - Columns: Category, Type, Count, Allocated, Spent, Remaining, Utilization
   - Sortable by category name

6. **Detailed Budget View Table** (main data):
   - Columns: Department, Fiscal Year, Period, Category, Type, Allocated, Spent, Remaining, Utilization, **Actions** (conditional)
   - Actions column visible only if `canManageBudgetRecords === true` (finance role or finance dept member)
   - Action buttons: Edit Budget (links to `/budgets/edit?id={budget_id}`)
   - Empty state: "No budget rows found for the selected scope."

---

#### Actions

| Action | Description | Trigger | Pre-condition | Post-condition | API/Service Called | Success Message | Error Handling |
|--------|-------------|---------|---------------|-----------------|-------------------|-----------------|-----------------|
| Filter & View | Apply filters and display matching budgets | Click "View" button | User has view permissions | Table refreshes with filtered data | `BudgetMonitorModel::getMonitorRows()` | N/A (silent update) | Show "No data found" |
| Edit Budget | Navigate to edit page for a specific budget row | Click "Edit Budget" link | Finance role or finance dept member; budget exists | Redirect to edit page with form pre-loaded | `BudgetController::edit()` | N/A (page load) | Redirect to monitor on error |
| Reset Filters | Clear all filters and reload page | Click "Reset" link | Page is in filtered state | All filters cleared; full dataset shown | Page reload | N/A (silent) | N/A |

---

#### Client-side Logic

**Field Dependencies:**
- Department filter automatically populated on page load based on user role (finance sees all; others see own)

**Dynamic Enable/Disable Rules:**
- Department dropdown disabled for non-finance users (pre-selected)
- Edit Budget button disabled if user lacks `canManageBudgetRecords` permission

**Conditional Visibility:**
- Actions column hidden if `canManageBudgetRecords === false`
- Department filter hidden if `isFinanceRole === false`

---

### Screen 2: Budget Edit

**Screen Name:** Budget Edit (Manual Edit Form)  
**Description:** Form page allowing authorized users to edit an existing budget row's fiscal year, period, category, amount, currency, and notes. Also includes "Back to Monitor", "New Budget Upload" buttons, and **"Delete Budget"** button with confirmation popup.

**Navigation Path:** `/budgets/edit?id={budget_id}` or `?route=budgets/edit&id={budget_id}`

**User Roles Allowed:**
- finance (organization-wide)
- employee, manager, dept_head (in finance department only)
- Anyone with budget management authorization

**Device/Platform:** Web  
**Responsive Behaviour:** Stacked form layout on mobile; action buttons flow

---

#### UI Elements

| Field Name | Label | Type | Control Type | Mandatory | Read Only | Default | Placeholder | Validation | Max Length | Format |
|-----------|-------|------|-------------|-----------|-----------|---------|-------------|-----------|-----------|--------|
| department_id | Department | Select | Dropdown | Yes | No | DB-loaded | – | Must be valid department | N/A | N/A |
| budget_fiscal_year | Fiscal Year | Text | Text Input | Yes | No | DB-loaded | e.g., "FY2026" | Non-empty, trimmed | 50 | YYYY or YYYY-Q# |
| budget_fiscal_period | Fiscal Period | Text | Text Input | Yes | No | DB-loaded | e.g., "Q1" | Non-empty, trimmed | 50 | Alphanumeric |
| budget_category_id | Budget Category | Select | Dropdown | Yes | No | DB-loaded | – | Must be valid category | N/A | N/A |
| budget_allocated_amount | Allocated Amount | Number | Number Input | Yes | No | DB-loaded | e.g., "50000.00" | > 0, numeric | N/A | Float (2 decimals) |
| budget_currency | Currency | Select | Dropdown | Yes | No | DB-loaded | – | Must be valid currency | N/A | 3-char code (INR, USD, etc.) |
| budget_notes | Notes | Text | Textarea | No | No | DB-loaded | "Update notes" | N/A | 1000 | Plain text |

**Tooltip:** 
- "Department": "Select or confirm the budget department"
- "Allocated Amount": "Must be greater than zero"
- "Currency": "Select currency for this budget"
- "Notes": "Optional notes about this budget (max 1000 chars)"

**Remarks:** All fields except Notes are required. Form is pre-populated from `BudgetModel::getBudgetById()`

---

#### Layout & Sections

**Header:**
- Page title: "Edit Budget"
- Subtitle: "Budget Management"

**Footer:**
- Standard site footer

**Sections/Panels:**
1. **Budget Details Section**:
   - Card header: "Budget Details"
   - Subtitle: "Update the selected budget row and save changes."
   - Form fields grid (responsive): Department, Fiscal Year, Fiscal Period, Budget Category, Allocated Amount, Currency, Notes

2. **Action Bar**:
   - Copy text: "Save changes to update budget in database"
   - Buttons layout:
     ```
     [Back to Budget Monitor] [New Budget Upload] [Delete Budget] [Update Budget]
     ```

---

#### Actions

| Action | Description | Trigger | Pre-condition | Post-condition | API/Service Called | Success Message | Error Handling |
|--------|-------------|---------|---------------|-----------------|-------------------|-----------------|-----------------|
| Update Budget | Validate and save changes to database | Click "Update Budget" button | Form valid; budget exists; user authorized | Redirect to monitor with success flash | `BudgetController::update()` | "Budget updated successfully." | Redirect to edit with error flash |
| Delete Budget | Remove budget row after confirmation | Click "Delete Budget" button | User authorized; confirm popup accepted | Redirect to monitor with success flash | `BudgetController::delete()` | "Budget deleted successfully." | Redirect to edit with error flash |
| Back to Monitor | Navigate to monitor page | Click "Back to Budget Monitor" | Page rendered | Redirect to budget-monitor | Page redirect | N/A | N/A |
| New Upload | Navigate to budget uploader | Click "New Budget Upload" | Page rendered | Redirect to budget-uploader | Page redirect | N/A | N/A |

---

#### Client-side Logic

**Field Dependencies:**
- Category dropdown populated from `BudgetCategoryModel::getAllCategories()`
- Department dropdown populated from `DepartmentModel::getAllDepartments()`
- Currency dropdown populated from `LookupModel::getRequestCurrencies()`

**Dynamic Enable/Disable Rules:**
- All fields enabled for editing (read-write)
- Submit buttons always enabled if user has authorization

**Conditional Visibility:**
- All fields visible for authorized users
- Actions hidden if user lacks authorization (permission check in controller)

**Delete Confirmation Popup:**
- Trigger: Click "Delete Budget" button
- Message: "Are you sure you want to delete this budget? This action cannot be undone."
- Buttons: OK (confirm), Cancel (abort)
- On confirm: Submit form to `budgets/delete?id={budget_id}`
- On cancel: Prevent form submission

---

### Screen 3: Budget Monitor – Web

**Name:** Budget Monitor (Summary View)  
**Description:** Aggregated monitoring dashboard showing budget allocations, spending, and utilization across the organization (finance role) or department (manager role).

*(See Screen 1 details above for full specification.)*

---

### Screen 4: Budget Uploader

**Screen Name:** Budget Uploader (CSV/Excel Upload)  
**Description:** File upload interface allowing finance users to bulk import budget rows from CSV, XLSX, or image files. Includes format specification, file picker, preview of parsed data, and row-level error feedback.

**Navigation Path:** `/budget-uploader` or `?route=budget-uploader`

**User Roles Allowed:**
- finance (organization-wide)
- employee, manager, dept_head (in finance department only)

**Device/Platform:** Web  
**Responsive Behaviour:** Stacked layout on mobile; file input expands

---

#### UI Elements

| Field Name | Label | Type | Control Type | Mandatory | Format | Validation |
|-----------|-------|------|-------------|-----------|--------|-----------|
| budget_file | Select Budget File | File | File Input | Yes | CSV, XLSX, XLS, JPG, PNG | Max 5MB; supported MIME types only |

*(Full details in separate Budget Uploader FSD section if needed)*

---

---

## Access Control & Security

### Roles & Permissions

| Role | Access Level | Screens | Actions | Scope |
|------|--------------|---------|---------|-------|
| **finance** | Full (organization-wide) | All budget screens, all expense screens | Create, Read, Update, Delete budgets; approve all expenses | All departments/budgets |
| **employee** (finance dept only) | Conditional | Budget Monitor (read), Budget Edit/Delete, Uploader | Create/edit/delete own-dept budgets; submit expenses | Finance dept only |
| **manager** (finance dept only) | Conditional | Budget Monitor (read), Budget Edit/Delete, Uploader | Create/edit/delete own-dept budgets; approve expenses | Finance dept budgets |
| **dept_head** (finance dept only) | Conditional | Budget Monitor (read), Budget Edit/Delete, Uploader | Create/edit/delete own-dept budgets; approve expenses | Finance dept budgets |
| **manager** (any dept) | Department | Budget Monitor (own dept), Expense Approvals | View own-dept budgets; approve expenses | Own dept only |
| **dept_head** (any dept) | Department | Budget Monitor (own dept), Expense Approvals | View own-dept budgets; approve expenses | Own dept only |
| **hr** | HR-scoped | User/Dept Management | Manage users, departments | All |

### Permission Keys (Canonical)

| Permission Key | Role(s) | Description |
|---|---|---|
| `budget_monitor.view` | all users | Access Budget Monitor page |
| `budget_monitor.view_all` | finance | See all departments in monitor (otherwise scoped to own dept) |
| `users.view` | finance, hr | View user list |
| `users.manage` | finance, hr | Create/edit/delete users |
| `departments.view` | finance, hr | View department list |
| `departments.manage` | finance, hr | Create/edit/delete departments |
| `budget_categories.view` | finance, hr | View budget categories |
| `budget_categories.manage` | finance, hr | Manage budget categories |
| `expenses.view` | finance, employees | View financial requests |
| `expenses.review` | managers, dept_heads | Review own-dept requests |
| `expenses.review_all` | finance | Review all requests |
| `workflows.list` | finance | View workflows |
| `workflows.manage` | finance | Create/edit workflows |

### Authorization Logic for Budget Management

```
canManageBudgetRecords():
  IF role === 'finance':
    return TRUE  // Organization-wide access
  IF department_name === 'finance' AND role IN ['employee', 'manager', 'dept_head']:
    return TRUE  // Finance dept members with appropriate role
  return FALSE
```

### Data Security

- **Database access control:** PDO prepared statements with parameterized queries prevent SQL injection
- **Row-level security:** Budget data scoped by department for non-finance users
- **Session-based auth:** User permissions loaded on login; checked on every request
- **Audit trail:** All budget modifications logged via `RbacService::audit()`
- **Input validation:** Server-side validation on all forms; client-side HTML5 validation for UX

---

---

## API Specifications

### API 1

**API Name:** Get Budget Monitor Data  
**Endpoint:** `/api/v1/budget-monitor`  
**Method:** GET  
**Description:** Retrieve aggregated budget data with optional filters and role-based scoping.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| fiscal_year | string | No | Fiscal year filter |
| type | string | No | Budget category type filter |
| department_id | integer | No | Department filter |
| category_id | integer | No | Budget category filter |

**Response**

| Field | Type | Description |
|---|---|---|
| data | array | Budget monitor rows |
| meta.scope_department_id | integer/null | Applied scope department |
| meta.is_finance_role | boolean | Finance role flag |
| meta.can_manage_budget_records | boolean | Edit/delete authorization flag |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Unauthorized |
| 403 | Forbidden |
| 422 | Invalid filter parameters |

### API 2

**API Name:** Get Single Budget Details  
**Endpoint:** `/api/v1/budgets?id={budget_id}` or `/api/v1/budget/edit?id={budget_id}`  
**Method:** GET  
**Description:** Fetch one budget row and lookup options for edit form.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| id | integer | Yes | Budget ID |

**Response**

| Field | Type | Description |
|---|---|---|
| data.budget | object | Selected budget details |
| data.options.departments | array | Department options |
| data.options.categories | array | Category options |
| data.options.currencies | array | Currency options |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Budget not found |
| 422 | Invalid budget id |

### API 3

**API Name:** Update Budget  
**Endpoint:** `/api/v1/budgets?id={budget_id}` or `/api/v1/budget/edit?id={budget_id}`  
**Method:** PUT, PATCH, POST  
**Description:** Update an existing budget row.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| id (query) | integer | Yes | Budget ID |
| department_id | integer | Yes | Department ID |
| budget_fiscal_year | string | Yes | Fiscal year |
| budget_fiscal_period | string | Yes | Fiscal period |
| budget_category_id | integer | Yes | Category ID |
| budget_allocated_amount | number | Yes | Allocated amount |
| budget_currency | string | Yes | Currency code |
| budget_notes | string | No | Budget notes |

**Response**

| Field | Type | Description |
|---|---|---|
| data.message | string | Success message |
| data.budget | object | Updated budget object |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Budget not found |
| 422 | Validation failed |
| 500 | Database update failed |

### API 4

**API Name:** Delete Budget  
**Endpoint:** `/api/v1/budgets?id={budget_id}` or `/api/v1/budget/delete?id={budget_id}` or `/api/v1/budgets/delete?id={budget_id}`  
**Method:** DELETE  
**Description:** Permanently delete a budget row.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| id (query) | integer | Yes | Budget ID |

**Response**

| Field | Type | Description |
|---|---|---|
| data.message | string | Success message |
| data.budget_id | integer | Deleted budget ID |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Budget not found |
| 422 | Invalid budget id |
| 500 | Database delete failed |

### API 5

**API Name:** Upload Budget File  
**Endpoint:** `/api/v1/budgets/upload` or `/api/v1/budget/upload`  
**Method:** POST  
**Description:** Upload and parse budget file, validate rows, and insert valid records.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| budget_file | file | Yes | Upload file (CSV/XLSX/XLS/JPG/JPEG/PNG) |

**Response**

| Field | Type | Description |
|---|---|---|
| data.inserted_count | integer | Number of inserted rows |
| data.warnings | array | Non-fatal parsing warnings |
| data.preview | array | Row-level status summary |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Unauthorized |
| 403 | Forbidden |
| 422 | File or row validation failed |
| 500 | Database commit failed |

### API 6

**API Name:** User Login  
**Endpoint:** `/api/v1/auth/login`  
**Method:** POST  
**Description:** Authenticate user and create session.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| email | string | Yes | User email |
| password | string | Yes | User password |

**Response**

| Field | Type | Description |
|---|---|---|
| data.user.user_id | integer | User ID |
| data.user.name | string | User full name |
| data.user.email | string | User email |
| data.user.role | string | User role |
| data.user.department_id | integer | Department ID |
| data.user.department_name | string | Department name |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Invalid credentials |
| 422 | Missing required fields |

### API 7

**API Name:** User Logout  
**Endpoint:** `/api/v1/auth/logout`  
**Method:** POST  
**Description:** Destroy active session.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| - | - | - | No request body required |

**Response**

| Field | Type | Description |
|---|---|---|
| data.message | string | Logout confirmation message |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Unauthorized session |

### API 8

**API Name:** Get Current User Info  
**Endpoint:** `/api/v1/auth/me`  
**Method:** GET  
**Description:** Return authenticated session payload.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| - | - | - | No request body required |

**Response**

| Field | Type | Description |
|---|---|---|
| data.auth | object | Authenticated session object |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Not authenticated |

### API 9

**API Name:** API Health  
**Endpoint:** `/api/v1/health`  
**Method:** GET  
**Description:** Health check endpoint.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| - | - | - | No request input |

**Response**

| Field | Type | Description |
|---|---|---|
| data.status | string | API status (`ok`) |

**Error Codes**

| Code | Message |
|---|---|
| 500 | Unexpected server error |

### API 10

**API Name:** Users Management  
**Endpoint:** `/api/v1/users` or `/api/v1/user?id={user_id}`  
**Method:** GET, POST, PUT, PATCH, DELETE  
**Description:** Create, read, update, and delete users.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| id (query) | integer | Conditional | Required for single user read/update/delete |
| name | string | Conditional | Required on create/update |
| email | string | Conditional | Required on create/update |
| role | string | Conditional | Required on create/update |
| department_id | integer | Conditional | Required on create/update |

**Response**

| Field | Type | Description |
|---|---|---|
| data | object/array | User record(s) or operation result |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | User not found |
| 422 | Validation failed |

### API 11

**API Name:** Departments Management  
**Endpoint:** `/api/v1/departments` or `/api/v1/department?id={id}`  
**Method:** GET, POST, PUT, PATCH, DELETE  
**Description:** Create, read, update, and delete departments.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| id (query) | integer | Conditional | Required for single record operations |
| department_name | string | Conditional | Required on create/update |
| department_code | string | Conditional | Required on create/update |

**Response**

| Field | Type | Description |
|---|---|---|
| data | object/array | Department record(s) or operation result |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Department not found |
| 422 | Validation failed |

### API 12

**API Name:** Budget Categories Management  
**Endpoint:** `/api/v1/budget-categories` or `/api/v1/budget-category?id={id}`  
**Method:** GET, POST, PUT, PATCH, DELETE  
**Description:** Create, read, update, and delete budget categories.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| id (query) | integer | Conditional | Required for single record operations |
| budget_category_name | string | Conditional | Required on create/update |
| budget_category_type | string | Conditional | Required on create/update |
| budget_category_is_active | integer | Conditional | Active flag |

**Response**

| Field | Type | Description |
|---|---|---|
| data | object/array | Category record(s) or operation result |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Category not found |
| 422 | Validation failed |

### API 13

**API Name:** Financial Requests Management  
**Endpoint:** `/api/v1/expenses` or `/api/v1/expense?id={id}`  
**Method:** GET, POST, PUT, PATCH  
**Description:** Create and manage financial requests.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| id (query) | integer | Conditional | Required for single request operations |
| request_title | string | Conditional | Required on create/update |
| request_amount | number | Conditional | Required on create/update |
| request_currency | string | Conditional | Currency code |
| budget_category_id | integer | Conditional | Linked budget category |

**Response**

| Field | Type | Description |
|---|---|---|
| data | object/array | Request record(s) or operation result |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Request not found |
| 422 | Validation failed |

### API 14

**API Name:** Expense Review  
**Endpoint:** `/api/v1/expenses/review?id={request_id}`  
**Method:** GET  
**Description:** Fetch expense request details for review screen.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| id (query) | integer | Yes | Financial request ID |

**Response**

| Field | Type | Description |
|---|---|---|
| data.request | object | Request details |
| data.attachments | array | Attachment metadata |
| data.workflow | object | Workflow/approval details |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Request not found |

### API 15

**API Name:** Workflows Management  
**Endpoint:** `/api/v1/workflows` or `/api/v1/workflow?id={id}`  
**Method:** GET, POST, PUT, PATCH, DELETE  
**Description:** Create, read, update, and delete approval workflows.

**Request**

| Field | Type | Required | Description |
|---|---|---|---|
| id (query) | integer | Conditional | Required for single workflow operations |
| workflow_name | string | Conditional | Required on create/update |
| workflow_type | string | Conditional | Workflow type |
| workflow_is_active | integer | Conditional | Active flag |
| steps | array | Conditional | Workflow step definitions |

**Response**

| Field | Type | Description |
|---|---|---|
| data | object/array | Workflow record(s) or operation result |

**Error Codes**

| Code | Message |
|---|---|
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Workflow not found |
| 422 | Validation failed |

---

## Database Design (High-Level)

### Core Tables

| Table | Columns | Purpose |
|-------|---------|---------|
| **users** | user_id, user_name, password_hash, name, email, role, department_id, created_at | User accounts and role assignments |
| **departments** | id, department_name, department_code, created_at | Organizational units |
| **roles** | role_id, role_slug, role_name, role_permissions (JSON) | Role definitions with canonical permission keys |
| **department_budgets** | budget_id, department_id, budget_fiscal_year, budget_fiscal_period, budget_category_id, budget_allocated_amount, budget_currency, budget_notes, budget_uploaded_by, budget_created_at, budget_updated_at | Budget allocations |
| **budget_categories** | budget_category_id, budget_category_name, budget_category_code, budget_category_type, budget_category_is_active, budget_category_created_by, created_at | Budget classification |
| **financial_requests** | request_id, request_type, request_title, request_description, request_amount, request_currency, request_priority, request_status, request_submitted_by, department_id, budget_category_id, workflow_id, request_submitted_at, created_at | Expense requests |
| **workflows** | workflow_id, workflow_type, workflow_name, workflow_is_active, created_at | Approval routing configs |
| **workflow_steps** | workflow_step_id, workflow_id, step_order, approver_role, created_at | Approval step definitions |
| **financial_request_attachments** | attachment_id, request_id, attachment_file_name, attachment_stored_name, attachment_mime_type, attachment_file_size, attachment_file_data, created_at | Expense attachments |
| **audit_logs** | audit_id, action, user_id, details (JSON), created_at | Compliance trail |

### Configurations

| Config Key | Description | Default Value | Environment |
|---|---|---|---|
| DB_HOST | Database server hostname | localhost | All |
| DB_PORT | Database server port | 3306 | All |
| DB_NAME | Database name | expense_register | All |
| DB_USER | Database user | root | Dev |
| DB_PASS | Database password | (empty) | Dev |
| APP_ENV | Environment | development | Set per environment |
| TIMEZONE | Server timezone | Asia/Kolkata | All |
| SESSION_TIMEOUT | Session expiry (minutes) | 120 | All |
| MAX_UPLOAD_SIZE | Max file upload (bytes) | 5242880 (5MB) | All |

---

---

## Error Handling & Logging

### Error Scenarios

| Scenario | Handling | User Message | Log Level |
|----------|----------|--------------|-----------|
| Invalid budget ID | Return 404; no data mutation | "Budget not found." | INFO |
| Missing required field | Return 422 with field errors | "Validation failed: [field] is required" | INFO |
| Unauthorized access | Return 403; redirect to forbidden page | "Access denied." | WARN |
| Database connection error | Return 500; rollback transaction | "System error. Please try again." | ERROR |
| File upload size exceeded | Return 422 with file size error | "File size exceeds 5MB limit." | WARN |
| Duplicate department code | Return 422 with constraint error | "Department code already exists." | INFO |
| Session expired | Return 401; redirect to login | "Session expired. Please login again." | INFO |
| CSV parsing error | Parse warnings; continue with valid rows | "Row 5: Invalid department ID" (in preview) | WARN |

### Logging Strategy

**For Developers:**
- Log all SQL queries in development environment (query_debug.log)
- Log API request/response bodies for endpoints (/api/logs/requests.log)
- Log RBAC permission checks and denials (/logs/rbac.log)
- Log database transaction begin/commit/rollback (/logs/transactions.log)
- Stack traces for exceptions to /logs/errors.log

**For Compliance/Audit:**
- `RbacService::audit()` logs all data modifications (budget_create, budget_update, budget_delete, request_submit, request_approve, etc.) to audit_logs table
- Audit entry includes: user_id, action, details (JSON), timestamp
- Retained indefinitely per compliance

---

---

## Assumptions & Constraints

### Assumptions

1. Users have a single role assignment (not multi-role)
2. Department names are unique organization-wide
3. Budget fiscal year/period formats are self-standardized (e.g., "FY2026", "Q1")
4. All monetary amounts are stored as floats with 2-decimal precision
5. Timestamps stored as UTC; displayed per user timezone (future enhancement)
6. Finance department always exists and is special (granted full access)
7. Users cannot modify their own roles or department assignments
8. Budget deletions are permanent (no soft delete)
9. File uploads processed synchronously (no queue system)

### Constraints

- Maximum 5MB file upload size
- Session timeout after 120 minutes of inactivity
- Database queries optimized for departments < 1000; category filters < 10000 rows
- Browser must support ES6 (arrow functions, Promise, fetch)
- CSS requires Bootstrap 5+ framework
- No client-side database sync; API is source of truth

---

---

## Dependencies

### External Libraries

| Library | Version | Purpose |
|---------|---------|---------|
| PHP | 7.4+ | Backend language |
| PDO | Native | Database abstraction |
| Bootstrap | 5.x | CSS framework |
| PHPSpreadsheet | ^1.28 | Excel/CSV parsing |
| (Optional) Chart.js | ^3.x | Budget visualization (future) |

### APIs / Services

- None (system is self-contained; no external integrations)

---

---

## Appendix

### Sample Data

#### Sample Budget
```json
{
  "budget_id": 1,
  "department_id": 3,
  "budget_fiscal_year": "FY2026",
  "budget_fiscal_period": "Q1",
  "budget_category_id": 7,
  "budget_allocated_amount": 50000.00,
  "budget_currency": "INR",
  "budget_notes": "Q1 travel budget for engineering",
  "budget_created_at": "2026-04-01T10:00:00Z"
}
```

#### Sample User
```json
{
  "user_id": 5,
  "user_name": "john.doe",
  "name": "John Doe",
  "email": "john@company.com",
  "role": "finance",
  "department_id": 1,
  "department_name": "Finance",
  "is_logged_in": true
}
```

#### Sample Permission Object
```json
{
  "budget_monitor.view": true,
  "budget_monitor.view_all": true,
  "users.view": true,
  "users.manage": true,
  "expenses.review_all": true
}
```

### Important Notes

1. **Auth Route Flow (Web):** `/login` (GET) -> `/auth` (POST) -> `/dashboard` (GET)
2. **Clean URLs:** All URLs support both clean format (`/budgets/edit?id=42`) and query fallback (`?route=budgets/edit&id=42`)
3. **Responsive Design:** All screens tested on mobile (320px), tablet (768px), desktop (1024px+)
4. **Audit Trail:** Every budget create/update/delete action logged with user_id, action name, timestamp
5. **Permission Inheritance:** Finance role inherits all permissions; department-scoped roles can only access own-dept data
6. **Data Types:**
   - Budget amounts: DECIMAL(12, 2)
   - Timestamps: TIMESTAMP (ISO 8601 in JSON)
   - Roles: VARCHAR(50) with predefined enum values
   - Permissions: JSON object stored in roles table
7. **Future Enhancements:**
   - Email notifications on request approval
   - Budget forecasting and trends analysis
   - Mobile app (iOS/Android)
   - Two-factor authentication
   - Advanced reporting and BI integration

---

**Document Version:** 1.0  
**Last Updated:** 14 April 2026  
**Next Review:** As per project timeline or upon major feature addition
