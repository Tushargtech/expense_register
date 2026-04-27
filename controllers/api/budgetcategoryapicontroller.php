<?php

class BudgetCategoryApiController extends ApiBaseController
{
    private function ensureListAccess(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canViewBudgetCategories(), 'Forbidden');
    }

    private function ensureCrudAccess(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canManageBudgetCategories(), 'Forbidden');
    }

    private function normalizePayload(array $source): array
    {
        return [
            'budget_category_name' => trim((string) ($source['budget_category_name'] ?? '')),
            'budget_category_code' => trim((string) ($source['budget_category_code'] ?? '')),
            'budget_category_type' => strtolower(trim((string) ($source['budget_category_type'] ?? ''))),
            'budget_category_description' => trim((string) ($source['budget_category_description'] ?? '')),
            'budget_category_is_active' => (int) ($source['budget_category_is_active'] ?? 1),
            'budget_category_created_by' => (int) ($this->authenticatedUser()['user_id'] ?? 0),
        ];
    }

    private function validatePayload(array $categoryData): array
    {
        $errors = [];
        $lookupModel = new LookupModel();
        $allowedTypes = $lookupModel->getBudgetCategoryTypes();
        if ($allowedTypes === [] && $categoryData['budget_category_type'] !== '') {
            $allowedTypes = [$categoryData['budget_category_type']];
        }

        if ($categoryData['budget_category_name'] === '') {
            $errors['budget_category_name'] = 'Category name is required.';
        }
        if ($categoryData['budget_category_code'] === '') {
            $errors['budget_category_code'] = 'Category code is required.';
        }
        if (!in_array($categoryData['budget_category_type'], $allowedTypes, true)) {
            $errors['budget_category_type'] = 'Category type is invalid.';
        }
        if (!in_array((int) $categoryData['budget_category_is_active'], [0, 1], true)) {
            $errors['budget_category_is_active'] = 'Invalid active flag.';
        }

        return $errors;
    }

    public function handle(): void
    {
        $method = $this->method();
        $id = $this->idFromQuery();

        if ($method === 'GET' && $id > 0) {
            $this->show($id);
            return;
        }

        if ($method === 'GET') {
            $this->index();
            return;
        }

        if ($method === 'POST' && $id <= 0) {
            $this->store();
            return;
        }

        if (in_array($method, ['PUT', 'PATCH', 'POST'], true) && $id > 0) {
            $this->update($id);
            return;
        }

        $this->jsonError('Method not allowed', 405);
    }

    public function index(): void
    {
        $this->ensureListAccess();
        $categoryModel = new BudgetCategoryModel();
        $filters = [
            'search' => $this->request->queryString('search'),
            'status' => $this->request->queryString('status'),
            'type' => $this->request->queryString('type'),
        ];

        $pageInfo = $this->pagination();
        $total = $categoryModel->countFilteredCategories($filters);
        $categories = $categoryModel->getFilteredCategories($filters, $pageInfo['limit'], $pageInfo['offset']);

        $this->jsonSuccess($categories, [
            'pagination' => [
                'page' => $pageInfo['page'],
                'limit' => $pageInfo['limit'],
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $pageInfo['limit'])),
            ],
        ]);
    }

    public function show(int $categoryId): void
    {
        $this->ensureListAccess();
        $categoryModel = new BudgetCategoryModel();
        $category = $categoryModel->getCategoryById($categoryId);

        if ($category === null) {
            $this->jsonError('Budget category not found.', 404);
        }

        $this->jsonSuccess($category);
    }

    public function store(): void
    {
        $this->ensureCrudAccess();
        $categoryData = $this->normalizePayload($this->input());
        $errors = $this->validatePayload($categoryData);

        if (!empty($errors)) {
            $this->jsonError('Validation failed.', 422, $errors);
        }

        $categoryModel = new BudgetCategoryModel();
        if (!$categoryModel->createCategory($categoryData)) {
            $this->jsonError('Failed to create budget category.', 500);
        }

        $createdId = 0;
        try {
            $stmt = getDB()->prepare('SELECT budget_category_id FROM budget_categories WHERE budget_category_code = :code ORDER BY budget_category_id DESC LIMIT 1');
            $stmt->execute([':code' => $categoryData['budget_category_code']]);
            $createdId = (int) ($stmt->fetchColumn() ?: 0);
        } catch (Throwable $error) {
            $createdId = 0;
        }

        RbacService::audit('budget_category_create', ['code' => $categoryData['budget_category_code']]);
        $this->jsonSuccess([
            'message' => 'Budget category created successfully.',
            'budget_category_id' => $createdId,
        ], [], 201);
    }

    public function update(int $categoryId): void
    {
        $this->ensureCrudAccess();
        if ($categoryId <= 0) {
            $this->jsonError('Invalid budget category id.', 422);
        }

        $categoryData = $this->normalizePayload($this->input());
        $errors = $this->validatePayload($categoryData);

        if (!empty($errors)) {
            $this->jsonError('Validation failed.', 422, $errors);
        }

        $categoryModel = new BudgetCategoryModel();
        if (!$categoryModel->updateCategory($categoryId, $categoryData)) {
            $this->jsonError('Failed to update budget category.', 500);
        }

        RbacService::audit('budget_category_update', ['budget_category_id' => $categoryId]);
        $this->jsonSuccess(['message' => 'Budget category updated successfully.']);
    }
}