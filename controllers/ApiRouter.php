<?php

class ApiRouter
{
    public function dispatch(string $route): void
    {
        $route = trim($route, '/');

        switch ($route) {
            case 'health':
                ApiResponse::success(['status' => 'ok']);
                break;

            case 'auth/login':
                (new AuthApiController())->login();
                break;

            case 'auth/logout':
                (new AuthApiController())->logout();
                break;

            case 'auth/me':
                (new AuthApiController())->me();
                break;

            case 'users':
            case 'user':
                (new UserApiController())->handle();
                break;

            case 'departments':
            case 'department':
                (new DepartmentApiController())->handle();
                break;

            case 'budget-categories':
            case 'budget-category':
                (new BudgetCategoryApiController())->handle();
                break;

            case 'budgets/upload':
            case 'budget/upload':
                (new BudgetApiController())->upload();
                break;

            case 'budgets':
            case 'budget':
            case 'budgets/edit':
            case 'budget/edit':
            case 'budgets/delete':
            case 'budget/delete':
                (new BudgetApiController())->handle();
                break;

            case 'budget-monitor':
                (new BudgetMonitorApiController())->index();
                break;

            case 'expenses':
            case 'expense':
                (new ExpenseApiController())->handle();
                break;

            case 'expenses/review':
            case 'expense/review':
                (new ExpenseApiController())->review();
                break;

            case 'expenses/attachment/view':
            case 'expense/attachment/view':
                (new ExpenseApiController())->viewAttachment();
                break;

            case 'expenses/action':
            case 'expense/action':
                (new ExpenseApiController())->action();
                break;

            case 'workflows':
            case 'workflow':
                (new WorkflowApiController())->handle();
                break;

            default:
                ApiResponse::error('API route not found', 404);
        }
    }
}