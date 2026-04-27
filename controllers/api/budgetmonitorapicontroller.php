<?php

class BudgetMonitorApiController extends ApiBaseController
{
    public function index(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canAccessBudgetMonitor(), 'Forbidden');

        $filters = [
            'fiscal_year' => $this->request->queryString('fiscal_year'),
            'type' => $this->request->queryString('type'),
            'department_id' => (int) ($this->request->routeParam('department_id', 0) ?? 0),
            'category_id' => (int) ($this->request->routeParam('category_id', 0) ?? 0),
        ];

        $isFinanceRole = $this->rbac()->canViewOrganizationBudgetUtilization();
        $departmentId = $this->rbac()->departmentId();
        $scopeDepartmentId = null;

        if (!$isFinanceRole && $departmentId > 0) {
            $scopeDepartmentId = $departmentId;
            $filters['department_id'] = $departmentId;
        }

        $model = new BudgetMonitorModel();
        $rows = $model->getMonitorRows(
            $filters,
            $scopeDepartmentId !== null ? $scopeDepartmentId : ($filters['department_id'] > 0 ? $filters['department_id'] : null)
        );

        $this->jsonSuccess($rows, [
            'scope_department_id' => $scopeDepartmentId,
            'is_finance_role' => $isFinanceRole,
            'can_manage_budget_records' => $this->rbac()->canManageBudgetRecords(),
        ]);
    }
}