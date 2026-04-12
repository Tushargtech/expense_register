<?php

require_once ROOT_PATH . '/libraries/BudgetFileParser.php';

class BudgetController
{
	private function rbac(): RbacService
	{
		return new RbacService();
	}

	private function ensureAuthenticated(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			flash_error('Please login to continue.');
			header('Location: ?route=dashboard');
			exit;
		}
	}

	private function isAuthorizedForBudgetUpload(): bool
	{
		return $this->rbac()->canManageFinancialSetup();
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

		$departmentReference = (string) (
			$row['department_id'] ??
			$row['department'] ??
			$row['department_code'] ??
			''
		);
		$departmentResolution = $budgetModel->resolveDepartment($departmentReference);
		$departmentId = $departmentResolution['department_id'];
		if ($departmentId === null) {
			$errors[] = 'Department "' . ($departmentReference ?: 'missing') . '" not found';
		}

		$categoryReference = (string) (
			$row['budget_category_id'] ??
			$row['budget_category'] ??
			$row['category'] ??
			''
		);
		$categoryType = (string) (
			$row['budget_category_type'] ??
			$row['category_type'] ??
			''
		);

		$categoryResolution = $budgetModel->resolveBudgetCategory($categoryReference, $categoryType);
		if ($categoryReference === '') {
			$errors[] = 'Budget Category is required';
		} elseif (($categoryResolution['budget_category_id'] ?? null) === null) {
			$errors[] = 'Budget Category "' . $categoryReference . '" not found';
		}

		$fiscalYear = trim((string) (
			$row['budget_fiscal_year'] ??
			$row['fiscal_year'] ??
			$row['year'] ??
			''
		));
		if ($fiscalYear === '') {
			$errors[] = 'Fiscal Year is required';
		}

		$fiscalPeriod = trim((string) (
			$row['budget_fiscal_period'] ??
			$row['fiscal_period'] ??
			$row['period'] ??
			''
		));
		if ($fiscalPeriod === '') {
			$errors[] = 'Fiscal Period is required';
		}

		$rawAmount = (string) (
			$row['budget_allocated_amount'] ??
			$row['allocated_amount'] ??
			$row['budget_amount'] ??
			$row['amount'] ??
			''
		);
		$amount = $this->parseAmount($rawAmount);
		if ($amount === null) {
			$errors[] = 'Allocated Amount must be a valid number';
		}

		$currency = strtoupper(trim((string) (
			$row['budget_currency'] ??
			$row['currency'] ??
			'INR'
		)));
		if ($currency === '') {
			$currency = 'INR';
		}

		$notes = trim((string) (
			$row['budget_notes'] ??
			$row['notes'] ??
			$row['description'] ??
			''
		));

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
			'budget_uploaded_by' => $uploadedBy,
		];

		return [$mapped, $errors];
	}

	public function index(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetUpload()) {
			header('Location: ?route=forbidden&code=rbac_budget_upload');
			exit;
		}

		$pageTitle = 'Budget Uploader - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/creation.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'budget-uploader';

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/budget_uploader.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}

	public function upload(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetUpload()) {
			header('Location: ?route=forbidden&code=rbac_budget_upload');
			exit;
		}

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=budget-uploader');
			exit;
		}

		if (!isset($_FILES['budget_file']) || !is_array($_FILES['budget_file'])) {
			flash_error('Please select a file to upload.');
			header('Location: ?route=budget-uploader');
			exit;
		}

		$file = $_FILES['budget_file'];
		$uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($uploadError !== UPLOAD_ERR_OK) {
			flash_error('Failed to upload file. Please ensure the file size is not too large and try again.');
			header('Location: ?route=budget-uploader');
			exit;
		}

		$originalName = (string) ($file['name'] ?? '');
		$tmpName = (string) ($file['tmp_name'] ?? '');
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

		$allowed = ['csv', 'xlsx', 'xls', 'jpg', 'jpeg', 'png'];
		if (!in_array($extension, $allowed, true)) {
			flash_error('Unsupported file format. Please use CSV, Excel, or Image (JPG/PNG) files.');
			header('Location: ?route=budget-uploader');
			exit;
		}

		$parser = new BudgetFileParser();
		$parseResult = $parser->parseUploadedFile($tmpName, $originalName);
		$rows = isset($parseResult['rows']) && is_array($parseResult['rows']) ? $parseResult['rows'] : [];
		$warnings = isset($parseResult['warnings']) && is_array($parseResult['warnings']) ? $parseResult['warnings'] : [];
		$errors = isset($parseResult['errors']) && is_array($parseResult['errors']) ? $parseResult['errors'] : [];

		if (empty($rows)) {
			$errorMessage = !empty($errors) ? $errors[0] : 'No data found in the file. Please ensure the file contains budget rows.';
			flash_error($errorMessage);
			header('Location: ?route=budget-uploader');
			exit;
		}

		$budgetModel = new BudgetModel();
		$uploadedBy = (int) ($_SESSION['auth']['user_id'] ?? 0);

		$insertedCount = 0;
		$skippedCount = 0;
		$rowErrors = [];
		$parsedPreview = [];
		$validRows = [];

		foreach ($rows as $index => $row) {
			if (!is_array($row)) {
				$skippedCount++;
				$rowErrors[] = ['row' => $index + 1, 'issues' => ['Cannot read row data']];
				$parsedPreview[] = [
					'row' => $index + 1,
					'status' => 'skipped',
					'data' => [],
					'issues' => ['Cannot read row data'],
				];
				continue;
			}

			[$mapped, $mappingErrors] = $this->mapToDatabaseSchema($row, $budgetModel, $uploadedBy);
			if (!empty($mappingErrors)) {
				$skippedCount++;
				$rowErrors[] = ['row' => $index + 1, 'issues' => $mappingErrors];
				$parsedPreview[] = [
					'row' => $index + 1,
					'status' => 'skipped',
					'data' => $mapped,
					'issues' => $mappingErrors,
				];
				continue;
			}

			$validRows[] = [
				'row' => $index + 1,
				'data' => $mapped,
			];
			$parsedPreview[] = [
				'row' => $index + 1,
				'status' => 'ready',
				'data' => $mapped,
				'issues' => [],
			];
		}

		if (!empty($rowErrors)) {
			$_SESSION['budget_uploader_preview'] = $parsedPreview;
			$formattedErrors = [];
			foreach (array_slice($rowErrors, 0, 5) as $errorSet) {
				$rowNum = $errorSet['row'];
				$issues = $errorSet['issues'];
				$formattedErrors[] = "Row $rowNum: " . implode(', ', $issues);
			}

			$totalRows = count($rows);
			$errorRows = count($rowErrors);
			$errorSummary = 'No data was saved. ' . $errorRows . ' of ' . $totalRows . ' rows have issues: ' . implode(' | ', $formattedErrors);
			if (count($rowErrors) > 5) {
				$errorSummary .= ' (...and ' . (count($rowErrors) - 5) . ' more)';
			}
			flash_error($errorSummary);

			header('Location: ?route=budget-uploader');
			exit;
		}

		$db = getDB();
		$db->beginTransaction();
		try {
			foreach ($validRows as $validRow) {
				$saved = $budgetModel->insertExtractedData($validRow['data']);
				if (!$saved) {
					throw new RuntimeException('Could not save row ' . $validRow['row'] . ' to database.');
				}
				$insertedCount++;
			}

			$db->commit();

			foreach ($parsedPreview as $i => $previewRow) {
				if (($previewRow['status'] ?? '') === 'ready') {
					$parsedPreview[$i]['status'] = 'success';
				}
			}

			$_SESSION['budget_uploader_preview'] = $parsedPreview;
			$successMessage = '✓ All ' . count($validRows) . ' row(s) successfully imported.';
			if (!empty($warnings)) {
				$successMessage .= ' Note: ' . $warnings[0];
			}
			RbacService::audit('budget_upload_import', ['rows' => count($validRows)]);
			flash_success($successMessage);
		} catch (Throwable $error) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}

			foreach ($parsedPreview as $i => $previewRow) {
				if (($previewRow['status'] ?? '') === 'ready') {
					$parsedPreview[$i]['status'] = 'skipped';
					$parsedPreview[$i]['issues'] = ['Database save failed'];
				}
			}

			$_SESSION['budget_uploader_preview'] = $parsedPreview;
			flash_error('No data was saved because database insert failed. Please try again.');
		}

		header('Location: ?route=budget-uploader');
		exit;
	}
}
