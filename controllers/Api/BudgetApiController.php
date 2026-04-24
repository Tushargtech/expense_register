<?php

require_once ROOT_PATH . '/libraries/BudgetFileParser.php';

class BudgetApiController extends ApiBaseController
{
    private function ensureAccess(): void
    {
        $this->ensureAuthenticated();
        $this->ensurePermission($this->rbac()->canManageFinancialSetup(), 'Forbidden');
    }

    public function handle(): void
    {
        $method = $this->method();
        $id = $this->idFromQuery();

        if ($method === 'GET' && $id > 0) {
            $this->show($id);
            return;
        }

        if (in_array($method, ['PUT', 'PATCH', 'POST'], true) && $id > 0) {
            $this->update($id);
            return;
        }

        if ($method === 'DELETE' && $id > 0) {
            $this->delete($id);
            return;
        }

        $this->jsonError('Method not allowed', 405);
    }

    private function normalizeUpdatePayload(array $source): array
    {
        return [
            'department_id' => (int) ($source['department_id'] ?? 0),
            'budget_fiscal_year' => trim((string) ($source['budget_fiscal_year'] ?? '')),
            'budget_fiscal_period' => trim((string) ($source['budget_fiscal_period'] ?? '')),
            'budget_category_id' => (int) ($source['budget_category_id'] ?? 0),
            'budget_allocated_amount' => trim((string) ($source['budget_allocated_amount'] ?? '')),
            'budget_currency' => strtoupper(trim((string) ($source['budget_currency'] ?? ''))),
            'budget_notes' => trim((string) ($source['budget_notes'] ?? '')),
        ];
    }

    private function validateUpdatePayload(array $budgetData): array
    {
        $errors = [];

        if ($budgetData['department_id'] <= 0) {
            $errors['department_id'] = 'Department is required.';
        }
        if ($budgetData['budget_fiscal_year'] === '') {
            $errors['budget_fiscal_year'] = 'Fiscal year is required.';
        }
        if ($budgetData['budget_fiscal_period'] === '') {
            $errors['budget_fiscal_period'] = 'Fiscal period is required.';
            } else {
            $allowedPeriods = ['Q1', 'Q2', 'Q3', 'Q4', 'annual'];
            if (!in_array($budgetData['budget_fiscal_period'], $allowedPeriods, true)) {
                $errors['budget_fiscal_period'] = 'Fiscal period must be one of: ' . implode(', ', $allowedPeriods) . '.';
            }
        }
        if ($budgetData['budget_category_id'] <= 0) {
            $errors['budget_category_id'] = 'Budget category is required.';
        }
        if (
            $budgetData['budget_allocated_amount'] === '' ||
            !is_numeric($budgetData['budget_allocated_amount']) ||
            (float) $budgetData['budget_allocated_amount'] <= 0
        ) {
            $errors['budget_allocated_amount'] = 'Allocated amount must be greater than zero.';
        }
        if ($budgetData['budget_currency'] === '') {
            $errors['budget_currency'] = 'Currency is required.';
        }

        return $errors;
    }

    private function parseAmount(string $rawAmount): ?float
    {
        $clean = preg_replace('/[^0-9.\-]/', '', trim($rawAmount)) ?? '';
        if ($clean === '' || !is_numeric($clean)) {
            return null;
        }

        return round((float) $clean, 2);
    }

    private function mapToDatabaseSchema(array $row, BudgetModel $budgetModel, int $uploadedBy): array
    {
        $errors = [];

        $departmentReference = (string) ($row['department_id'] ?? $row['department'] ?? $row['department_code'] ?? '');
        $departmentResolution = $budgetModel->resolveDepartment($departmentReference);
        $departmentId = $departmentResolution['department_id'];
        if ($departmentId === null) {
            $errors[] = 'Department "' . ($departmentReference ?: 'missing') . '" not found';
        }

        $categoryReference = (string) ($row['budget_category_id'] ?? $row['budget_category'] ?? $row['category'] ?? '');
        $categoryType = (string) ($row['budget_category_type'] ?? $row['category_type'] ?? '');
        $categoryResolution = $budgetModel->resolveBudgetCategory($categoryReference, $categoryType);
        if ($categoryReference === '') {
            $errors[] = 'Budget Category is required';
        } elseif (($categoryResolution['budget_category_id'] ?? null) === null) {
            $errors[] = 'Budget Category "' . $categoryReference . '" not found';
        }

        $fiscalYear = trim((string) ($row['budget_fiscal_year'] ?? $row['fiscal_year'] ?? $row['year'] ?? ''));
        if ($fiscalYear === '') {
            $errors[] = 'Fiscal Year is required';
        }

        $fiscalPeriod = trim((string) ($row['budget_fiscal_period'] ?? $row['fiscal_period'] ?? $row['period'] ?? ''));
        if ($fiscalPeriod === '') {
            $errors[] = 'Fiscal Period is required';
        }

        $rawAmount = (string) ($row['budget_allocated_amount'] ?? $row['allocated_amount'] ?? $row['budget_amount'] ?? $row['amount'] ?? '');
        $amount = $this->parseAmount($rawAmount);
        if ($amount === null) {
            $errors[] = 'Allocated Amount must be a valid number';
        }

        $currency = strtoupper(trim((string) ($row['budget_currency'] ?? $row['currency'] ?? 'INR')));
        if ($currency === '') {
            $currency = 'INR';
        }

        $notes = trim((string) ($row['budget_notes'] ?? $row['notes'] ?? $row['description'] ?? ''));

        $mapped = [
            'department_id' => $departmentId,
            'department_name' => $departmentResolution['department_name'],
            'budget_fiscal_year' => $fiscalYear,
            'budget_fiscal_period' => $fiscalPeriod,
            'budget_category' => $categoryResolution['budget_category'],
            'budget_category_id' => $categoryResolution['budget_category_id'],
            'budget_allocated_amount' => $amount,
            'budget_currency' => $currency,
            'budget_notes' => $notes,
            'budget_uploaded_by' => $uploadedBy > 0 ? $uploadedBy : null,
        ];

        return [$mapped, $errors];
    }

    public function upload(): void
    {
        $this->ensureAccess();

        if ($this->method() === 'GET') {
            $this->jsonSuccess([
                'supported_formats' => ['csv', 'xlsx', 'xls'],
                'max_file_size_bytes' => 5242880,
                'mode' => 'multipart/form-data',
            ]);
        }

        if ($this->method() !== 'POST') {
            $this->jsonError('Method not allowed', 405);
        }

        if (!isset($_FILES['budget_file']) || !is_array($_FILES['budget_file'])) {
            $this->jsonError('Please select a file to upload.', 422);
        }

        $file = $_FILES['budget_file'];
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $this->jsonError('Failed to upload file.', 422);
        }

        $originalName = (string) ($file['name'] ?? '');
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['csv', 'xlsx', 'xls'];
        if (!in_array($extension, $allowed, true)) {
            $this->jsonError('Unsupported file format. Please use CSV or Excel files.', 422);
        }

        $parser = new BudgetFileParser();
        $parseResult = $parser->parseUploadedFile($tmpName, $originalName);
        $rows = isset($parseResult['rows']) && is_array($parseResult['rows']) ? $parseResult['rows'] : [];
        $warnings = isset($parseResult['warnings']) && is_array($parseResult['warnings']) ? $parseResult['warnings'] : [];
        $errors = isset($parseResult['errors']) && is_array($parseResult['errors']) ? $parseResult['errors'] : [];

        if (empty($rows)) {
            $this->jsonError(!empty($errors) ? (string) $errors[0] : 'No data found in the file.', 422);
        }

        $budgetModel = new BudgetModel();
        $uploadedBy = (int) ($this->authenticatedUser()['user_id'] ?? 0);
        $db = getDB();

        $insertedCount = 0;
        $rowErrors = [];
        $preview = [];
        $validRows = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $rowErrors[] = ['row' => $index + 1, 'issues' => ['Cannot read row data']];
                $preview[] = ['row' => $index + 1, 'status' => 'skipped', 'data' => [], 'issues' => ['Cannot read row data']];
                continue;
            }

            [$mapped, $mappingErrors] = $this->mapToDatabaseSchema($row, $budgetModel, $uploadedBy);
            if (!empty($mappingErrors)) {
                $rowErrors[] = ['row' => $index + 1, 'issues' => $mappingErrors];
                $preview[] = ['row' => $index + 1, 'status' => 'skipped', 'data' => $mapped, 'issues' => $mappingErrors];
                continue;
            }

            $validRows[] = ['row' => $index + 1, 'data' => $mapped];
            $preview[] = ['row' => $index + 1, 'status' => 'ready', 'data' => $mapped, 'issues' => []];
        }

        if (!empty($rowErrors)) {
            $this->jsonError('No data was saved. Fix row issues and try again.', 422, ['rows' => $rowErrors, 'preview' => $preview]);
        }

        $db->beginTransaction();
        try {
            foreach ($validRows as $validRow) {
                if (!$budgetModel->insertExtractedData($validRow['data'])) {
                    throw new RuntimeException('Could not save row ' . $validRow['row'] . ' to database.');
                }
                $insertedCount++;
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->jsonError('No data was saved because database insert failed.', 500);
        }

        RbacService::audit('budget_upload_import', ['rows' => $insertedCount]);
        $this->jsonSuccess([
            'inserted_count' => $insertedCount,
            'warnings' => $warnings,
            'preview' => $preview,
        ], [], 201);
    }

    public function show(int $budgetId): void
    {
        $this->ensureAccess();
        if ($budgetId <= 0) {
            $this->jsonError('Invalid budget id.', 422);
        }

        $budgetModel = new BudgetModel();
        $budget = $budgetModel->getBudgetById($budgetId);
        if ($budget === null) {
            $this->jsonError('Budget row not found.', 404);
        }

        $departmentModel = new DepartmentModel();
        $budgetCategoryModel = new BudgetCategoryModel();
        $lookupModel = new LookupModel();

        $departments = $departmentModel->getAllDepartments();
        $categories = $budgetCategoryModel->getAllCategories();
        $currencyOptions = $lookupModel->getRequestCurrencies();
        if ($currencyOptions === [] && trim((string) ($budget['budget_currency'] ?? '')) !== '') {
            $currencyOptions = [strtoupper(trim((string) ($budget['budget_currency'] ?? '')))];
        }

        $this->jsonSuccess([
            'budget' => $budget,
            'options' => [
                'departments' => $departments,
                'categories' => $categories,
                'currencies' => $currencyOptions,
            ],
        ]);
    }

    public function update(int $budgetId): void
    {
        $this->ensureAccess();
        if ($budgetId <= 0) {
            $this->jsonError('Invalid budget id.', 422);
        }

        $budgetModel = new BudgetModel();
        $existing = $budgetModel->getBudgetById($budgetId);
        if ($existing === null) {
            $this->jsonError('Budget row not found.', 404);
        }

        $budgetData = $this->normalizeUpdatePayload($this->input());
        $validationErrors = $this->validateUpdatePayload($budgetData);
        if (!empty($validationErrors)) {
            $this->jsonError('Validation failed.', 422, $validationErrors);
        }

        $categoryResolution = $budgetModel->resolveBudgetCategory((string) $budgetData['budget_category_id']);
        if ((int) ($categoryResolution['budget_category_id'] ?? 0) <= 0) {
            $this->jsonError('Validation failed.', 422, ['budget_category_id' => 'Selected budget category is invalid.']);
        }

        $budgetData['budget_category'] = (string) ($categoryResolution['budget_category'] ?? '');
        $budgetData['budget_allocated_amount'] = (float) $budgetData['budget_allocated_amount'];

        if (!$budgetModel->updateBudget($budgetId, $budgetData)) {
            $this->jsonError('Failed to update budget.', 500);
        }

        RbacService::audit('budget_update', ['budget_id' => $budgetId]);
        $updated = $budgetModel->getBudgetById($budgetId);

        $this->jsonSuccess([
            'message' => 'Budget updated successfully.',
            'budget' => $updated,
        ]);
    }

    public function delete(int $budgetId): void
    {
        $this->ensureAccess();
        if ($budgetId <= 0) {
            $this->jsonError('Invalid budget id.', 422);
        }

        $budgetModel = new BudgetModel();
        $budget = $budgetModel->getBudgetById($budgetId);
        if ($budget === null) {
            $this->jsonError('Budget row not found.', 404);
        }

        if (!$budgetModel->deleteBudget($budgetId)) {
            $this->jsonError('Failed to delete budget.', 500);
        }

        RbacService::audit('budget_delete', ['budget_id' => $budgetId]);
        $this->jsonSuccess([
            'message' => 'Budget deleted successfully.',
            'budget_id' => $budgetId,
        ]);
    }
}