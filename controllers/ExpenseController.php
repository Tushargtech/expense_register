<?php

class ExpenseController
{
    private $model;
    private $rbac;

    public function __construct()
    {
        $this->model = new ExpensesModel();
        $this->rbac = new RbacService();
    }

    public function list()
    {
        if (!$this->rbac->canAccessFinancialRequests()) {
            header('Location: ?route=forbidden');
            exit;
        }

        $page = (int) ($_GET['page'] ?? 1);
        $perPage = 15;

        $filters = [
            'search' => $_GET['search'] ?? '',
            'type' => $_GET['type'] ?? '',
            'department' => $_GET['department'] ?? '',
            'status' => $_GET['status'] ?? 'pending',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];

        $data = $this->model->getExpenses($filters, $page, $perPage);

        // Get departments for filter dropdown
        $departmentModel = new DepartmentModel();
        $departments = $departmentModel->getAllDepartments();

        $canCreateExpense = $this->rbac->canAccessFinancialRequests();

        extract(array_merge($data, [
            'filters' => $filters,
            'currentPage' => $page,
            'perPage' => $perPage,
            'departments' => $departments,
            'canCreateExpense' => $canCreateExpense,
        ]));

        require ROOT_PATH . '/views/templates/app_layout.php';
        renderAppLayoutStart(['activeMenu' => 'expense-list', 'pageTitle' => 'Expenses']);
        require ROOT_PATH . '/views/ExpenseManagement/expense_list.php';
        renderAppLayoutEnd();
    }

    // Other methods like create, store, review, etc. can be added here
}