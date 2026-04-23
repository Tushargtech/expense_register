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
		return $this->rbac()->canManageBudgetRecords();
	}

	private function isAuthorizedForBudgetEdit(): bool
	{
		return $this->rbac()->canManageBudgetRecords();
	}

	private function parseAmount(string $rawAmount): ?float
	{
		$clean = preg_replace('/[^0-9.\-]/', '', trim($rawAmount)) ?? '';
		if ($clean === '' || !is_numeric($clean)) {
			return null;
		}

		return round((float) $clean, 2);
	}

	private function getCurrentIstDate(): string
	{
		$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

		return $istNow->format('Y-m-d');
	}

	private function notifyDepartmentHeadBudgetUpdate(
		int $departmentId,
		string $budgetHead,
		string $fiscalYear,
		string $fiscalPeriod,
		string $currency,
		float $currentLimit,
		float $previousLimit,
		string $actionType
	): void {
		if ($departmentId <= 0) {
			return;
		}

		$departmentModel = new DepartmentModel();
		$department = $departmentModel->getDepartmentById($departmentId);
		if (!is_array($department)) {
			return;
		}

		$departmentHeadEmail = trim((string) ($department['head_email'] ?? ''));
		if ($departmentHeadEmail === '') {
			return;
		}

		$departmentHeadName = trim((string) ($department['head_name'] ?? 'Department Head'));
		$departmentName = trim((string) ($department['department_name'] ?? 'Department'));
		$differenceAmount = $currentLimit - $previousLimit;
		$differenceAmountFormatted = ($differenceAmount >= 0 ? '+' : '-') . number_format(abs($differenceAmount), 2, '.', '');

		$mailService = new MailService();
		$sent = $mailService->sendBudgetUpdateNotificationEmail(
			$departmentHeadEmail,
			$departmentHeadName !== '' ? $departmentHeadName : 'Department Head',
			$departmentName !== '' ? $departmentName : 'Department',
			$budgetHead !== '' ? $budgetHead : 'Budget',
			$fiscalYear,
			$fiscalPeriod,
			$currency !== '' ? $currency : 'INR',
			number_format($currentLimit, 2, '.', ''),
			number_format($previousLimit, 2, '.', ''),
			$differenceAmountFormatted,
			$this->getCurrentIstDate(),
			strtolower(trim($actionType)) === 'created' ? 'created' : 'updated'
		);

		if (!$sent) {
			error_log('Failed to send budget update notification to department head for department ' . $departmentId);
		}
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
			'budget_uploaded_by' => $uploadedBy > 0 ? $uploadedBy : null,
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
		$pageStyles = ['assets/css/app.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'budget-uploader';

		require ROOT_PATH . '/views/templates/app_layout.php';
		renderAppLayoutStart([
			'pageTitle' => $pageTitle,
			'pageStyles' => $pageStyles,
			'activeMenu' => $activeMenu,
		]);
		require ROOT_PATH . '/views/BudgetManagement/budget_uploader.php';
		renderAppLayoutEnd();
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

		$intent = strtolower(trim((string) ($_POST['upload_intent'] ?? 'preview')));

		if ($intent === 'cancel') {
			unset($_SESSION['budget_uploader_staged'], $_SESSION['budget_uploader_preview'], $_SESSION['budget_uploader_show_preview_once']);
			header('Location: ?route=budget-uploader');
			exit;
		}

		if ($intent === 'confirm') {
			$staged = isset($_SESSION['budget_uploader_staged']) && is_array($_SESSION['budget_uploader_staged'])
				? $_SESSION['budget_uploader_staged']
				: [];
			$validRows = isset($staged['valid_rows']) && is_array($staged['valid_rows'])
				? $staged['valid_rows']
				: [];
			$parsedPreview = isset($staged['preview']) && is_array($staged['preview'])
				? $staged['preview']
				: [];
			$warnings = isset($staged['warnings']) && is_array($staged['warnings'])
				? $staged['warnings']
				: [];
			$hasErrors = !empty($staged['has_errors']);
			$hasDuplicates = !empty($staged['has_duplicates']);
			$overwriteExisting = !empty($_POST['overwrite_existing']);

			if (empty($validRows) || $hasErrors) {
				flash_error('Please select a valid CSV/Excel file and preview it before confirming upload.');
				header('Location: ?route=budget-uploader');
				exit;
			}

			if ($hasDuplicates && !$overwriteExisting) {
				flash_error('Some budget rows already exist for the same department, category, fiscal year and fiscal period. Choose Upload and Update Existing to proceed.');
				header('Location: ?route=budget-uploader');
				exit;
			}

			$budgetModel = new BudgetModel();
			$budgetMonitorController = new BudgetMonitorController();
			$insertedCount = 0;
			$updatedCount = 0;
			$alertBudgetIds = [];
			$budgetUpdateNotifications = [];
			$db = getDB();
			$db->beginTransaction();
			try {
				foreach ($validRows as $validRow) {
					$rowNum = (int) ($validRow['row'] ?? 0);
					$rowData = (array) ($validRow['data'] ?? []);
					$existingBudget = isset($validRow['existing_budget']) && is_array($validRow['existing_budget'])
						? $validRow['existing_budget']
						: null;

					if (is_array($existingBudget) && (int) ($existingBudget['budget_id'] ?? 0) > 0) {
						$existingBudgetId = (int) $existingBudget['budget_id'];
						$previousLimit = (float) ($existingBudget['budget_allocated_amount'] ?? 0);
						$updated = $budgetModel->updateBudget($existingBudgetId, $rowData);
						if (!$updated) {
							throw new RuntimeException('Could not update existing budget for row ' . $rowNum . '.');
						}
						$alertBudgetIds[] = $existingBudgetId;
						$budgetUpdateNotifications[] = [
							'department_id' => (int) ($rowData['department_id'] ?? 0),
							'budget_head' => (string) ($rowData['budget_category'] ?? ''),
							'fiscal_year' => (string) ($rowData['budget_fiscal_year'] ?? ''),
							'fiscal_period' => (string) ($rowData['budget_fiscal_period'] ?? ''),
							'currency' => (string) ($rowData['budget_currency'] ?? 'INR'),
							'current_limit' => (float) ($rowData['budget_allocated_amount'] ?? 0),
							'previous_limit' => $previousLimit,
							'action_type' => 'updated',
						];
						$updatedCount++;
						continue;
					}

					$saved = $budgetModel->insertExtractedData($rowData);
					if (!$saved) {
						$insertError = trim((string) ($budgetModel->getLastInsertError() ?? ''));
						if ($insertError === '') {
							$insertError = 'Unknown insert error.';
						}
						throw new RuntimeException('Could not save row ' . $rowNum . ': ' . $insertError);
					}

					$insertedBudget = $budgetModel->findExistingBudgetByScope(
						(int) ($rowData['department_id'] ?? 0),
						(int) ($rowData['budget_category_id'] ?? 0),
						(string) ($rowData['budget_fiscal_year'] ?? ''),
						(string) ($rowData['budget_fiscal_period'] ?? '')
					);
					if (is_array($insertedBudget) && (int) ($insertedBudget['budget_id'] ?? 0) > 0) {
						$alertBudgetIds[] = (int) $insertedBudget['budget_id'];
					}

					$budgetUpdateNotifications[] = [
						'department_id' => (int) ($rowData['department_id'] ?? 0),
						'budget_head' => (string) ($rowData['budget_category'] ?? ''),
						'fiscal_year' => (string) ($rowData['budget_fiscal_year'] ?? ''),
						'fiscal_period' => (string) ($rowData['budget_fiscal_period'] ?? ''),
						'currency' => (string) ($rowData['budget_currency'] ?? 'INR'),
						'current_limit' => (float) ($rowData['budget_allocated_amount'] ?? 0),
						'previous_limit' => 0.0,
						'action_type' => 'created',
					];
					$insertedCount++;
				}

				$db->commit();

				foreach (array_values(array_unique(array_filter($alertBudgetIds))) as $budgetId) {
					$budgetMonitorController->dispatchBudgetThresholdAlertForBudgetId((int) $budgetId);
				}

				foreach ($budgetUpdateNotifications as $notificationData) {
					$this->notifyDepartmentHeadBudgetUpdate(
						(int) ($notificationData['department_id'] ?? 0),
						(string) ($notificationData['budget_head'] ?? ''),
						(string) ($notificationData['fiscal_year'] ?? ''),
						(string) ($notificationData['fiscal_period'] ?? ''),
						(string) ($notificationData['currency'] ?? 'INR'),
						(float) ($notificationData['current_limit'] ?? 0),
						(float) ($notificationData['previous_limit'] ?? 0),
						(string) ($notificationData['action_type'] ?? 'updated')
					);
				}

				foreach ($parsedPreview as $i => $previewRow) {
					if (($previewRow['status'] ?? '') === 'ready') {
						$existingBudget = isset($previewRow['existing_budget']) && is_array($previewRow['existing_budget'])
							? $previewRow['existing_budget']
							: null;
						$parsedPreview[$i]['status'] = is_array($existingBudget) && (int) ($existingBudget['budget_id'] ?? 0) > 0 ? 'updated' : 'success';
					}
				}

				unset($_SESSION['budget_uploader_staged'], $_SESSION['budget_uploader_preview'], $_SESSION['budget_uploader_show_preview_once']);

				$successMessage = 'Budget upload completed. Inserted: ' . $insertedCount . ', Updated: ' . $updatedCount . '.';
				if (!empty($warnings)) {
					$successMessage .= ' Note: ' . (string) $warnings[0];
				}
				RbacService::audit('budget_upload_import', ['rows' => $insertedCount, 'updated' => $updatedCount]);
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

				unset($_SESSION['budget_uploader_staged'], $_SESSION['budget_uploader_preview'], $_SESSION['budget_uploader_show_preview_once']);
				$errorMessage = trim($error->getMessage());
				if ($errorMessage === '') {
					$errorMessage = 'Database insert failed.';
				}
				flash_error('No data was saved because database insert failed. ' . $errorMessage);
			}

			header('Location: ?route=budget-uploader');
			exit;
		}

		if (!isset($_FILES['budget_file']) || !is_array($_FILES['budget_file'])) {
			flash_error('Please select a CSV or Excel file to preview.');
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

		$allowed = ['csv', 'xlsx', 'xls'];
		if (!in_array($extension, $allowed, true)) {
			flash_error('Unsupported file format. Please use CSV or Excel files only.');
			header('Location: ?route=budget-uploader');
			exit;
		}

		$parser = new BudgetFileParser();
		$parseResult = $parser->parseUploadedFile($tmpName, $originalName);
		$rows = isset($parseResult['rows']) && is_array($parseResult['rows']) ? $parseResult['rows'] : [];
		$warnings = isset($parseResult['warnings']) && is_array($parseResult['warnings']) ? $parseResult['warnings'] : [];
		$errors = isset($parseResult['errors']) && is_array($parseResult['errors']) ? $parseResult['errors'] : [];

		if (empty($rows)) {
			unset($_SESSION['budget_uploader_staged'], $_SESSION['budget_uploader_preview'], $_SESSION['budget_uploader_show_preview_once']);
			$errorMessage = !empty($errors) ? (string) $errors[0] : 'No data found in the file. Please ensure the file contains budget rows.';
			flash_error($errorMessage);
			header('Location: ?route=budget-uploader');
			exit;
		}

		$budgetModel = new BudgetModel();
		$uploadedBy = (int) ($_SESSION['auth']['user_id'] ?? 0);
		$rowErrors = [];
		$parsedPreview = [];
		$validRows = [];
		$duplicateRows = 0;

		foreach ($rows as $index => $row) {
			if (!is_array($row)) {
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
				'existing_budget' => $budgetModel->findExistingBudgetByScope(
					(int) ($mapped['department_id'] ?? 0),
					(int) ($mapped['budget_category_id'] ?? 0),
					(string) ($mapped['budget_fiscal_year'] ?? ''),
					(string) ($mapped['budget_fiscal_period'] ?? '')
				),
			];
			$existingBudget = $validRows[count($validRows) - 1]['existing_budget'];
			if (is_array($existingBudget) && (int) ($existingBudget['budget_id'] ?? 0) > 0) {
				$duplicateRows++;
			}
			$parsedPreview[] = [
				'row' => $index + 1,
				'status' => 'ready',
				'data' => $mapped,
				'existing_budget' => $existingBudget,
				'issues' => [],
			];
		}

		$_SESSION['budget_uploader_preview'] = $parsedPreview;
		$_SESSION['budget_uploader_show_preview_once'] = 1;
		$hasErrors = !empty($rowErrors);
		if (!$hasErrors && !empty($validRows)) {
			$_SESSION['budget_uploader_staged'] = [
				'valid_rows' => $validRows,
				'preview' => $parsedPreview,
				'warnings' => $warnings,
				'has_errors' => false,
				'has_duplicates' => $duplicateRows > 0,
				'duplicate_rows' => $duplicateRows,
				'source_name' => $originalName,
				'total_rows' => count($rows),
				'created_at' => time(),
			];
			if ($duplicateRows > 0) {
				flash_success('Preview is ready. ' . $duplicateRows . ' row(s) already exist and can be updated by selecting Upload and Update Existing.');
			} else {
				flash_success('Preview is ready. Please review the rows and click Upload Budget to save.');
			}
		} else {
			unset($_SESSION['budget_uploader_staged']);
			flash_error('There are some changes needed in the budget upload file so kindly see the preview below.');
		}

		header('Location: ?route=budget-uploader');
		exit;
	}

	public function edit(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetEdit()) {
			header('Location: ?route=forbidden&code=rbac_budget_edit');
			exit;
		}

		flash_error('This action has been removed.');
		header('Location: ?route=budget-monitor');
		exit;
	}

	public function update(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetEdit()) {
			header('Location: ?route=forbidden&code=rbac_budget_edit');
			exit;
		}

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=budget-monitor');
			exit;
		}

		$budgetId = (int) ($_GET['id'] ?? 0);
		if ($budgetId <= 0) {
			flash_error('Invalid budget id.');
			header('Location: ?route=budget-monitor');
			exit;
		}

		$budgetData = [
			'department_id' => (int) ($_POST['department_id'] ?? 0),
			'budget_fiscal_year' => trim((string) ($_POST['budget_fiscal_year'] ?? '')),
			'budget_fiscal_period' => trim((string) ($_POST['budget_fiscal_period'] ?? '')),
			'budget_category_id' => (int) ($_POST['budget_category_id'] ?? 0),
			'budget_allocated_amount' => trim((string) ($_POST['budget_allocated_amount'] ?? '')),
			'budget_currency' => strtoupper(trim((string) ($_POST['budget_currency'] ?? ''))),
			'budget_notes' => trim((string) ($_POST['budget_notes'] ?? '')),
		];

		if (
			$budgetData['department_id'] <= 0 ||
			$budgetData['budget_fiscal_year'] === '' ||
			$budgetData['budget_fiscal_period'] === '' ||
			$budgetData['budget_category_id'] <= 0 ||
			$budgetData['budget_allocated_amount'] === '' ||
			!is_numeric($budgetData['budget_allocated_amount']) ||
			(float) $budgetData['budget_allocated_amount'] <= 0 ||
			$budgetData['budget_currency'] === ''
		) {
			flash_error('Please fill all required fields with valid values.');
			header('Location: ?route=budget-monitor');
			exit;
		}

		$budgetModel = new BudgetModel();
		$budgetMonitorController = new BudgetMonitorController();
		$existingBudget = $budgetModel->getBudgetById($budgetId);
		if (!is_array($existingBudget)) {
			flash_error('Budget row not found.');
			header('Location: ?route=budget-monitor');
			exit;
		}
		$categoryResolution = $budgetModel->resolveBudgetCategory((string) $budgetData['budget_category_id']);
		if ((int) ($categoryResolution['budget_category_id'] ?? 0) <= 0) {
			flash_error('Selected budget category is invalid.');
			header('Location: ?route=budget-monitor');
			exit;
		}

		$budgetData['budget_category'] = (string) ($categoryResolution['budget_category'] ?? '');
		$budgetData['budget_allocated_amount'] = (float) $budgetData['budget_allocated_amount'];

		$updated = $budgetModel->updateBudget($budgetId, $budgetData);
		if (!$updated) {
			flash_error('Failed to update budget.');
			header('Location: ?route=budget-monitor');
			exit;
		}

		$budgetMonitorController->dispatchBudgetThresholdAlertForBudgetId($budgetId);
		$this->notifyDepartmentHeadBudgetUpdate(
			(int) ($budgetData['department_id'] ?? 0),
			(string) ($budgetData['budget_category'] ?? ''),
			(string) ($budgetData['budget_fiscal_year'] ?? ''),
			(string) ($budgetData['budget_fiscal_period'] ?? ''),
			(string) ($budgetData['budget_currency'] ?? 'INR'),
			(float) ($budgetData['budget_allocated_amount'] ?? 0),
			(float) ($existingBudget['budget_allocated_amount'] ?? 0),
			'updated'
		);

		RbacService::audit('budget_update', ['budget_id' => $budgetId]);
		flash_success('Budget updated successfully.');
		header('Location: ?route=budget-monitor');
		exit;
	}

	public function delete(): void
	{
		$this->ensureAuthenticated();

		if (!$this->isAuthorizedForBudgetEdit()) {
			header('Location: ?route=forbidden&code=rbac_budget_delete');
			exit;
		}

		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			header('Location: ?route=budget-monitor');
			exit;
		}

		$budgetId = (int) ($_GET['id'] ?? 0);
		if ($budgetId <= 0) {
			flash_error('Invalid budget id.');
			header('Location: ?route=budget-monitor');
			exit;
		}

		$budgetModel = new BudgetModel();
		$budget = $budgetModel->getBudgetById($budgetId);
		if ($budget === null) {
			flash_error('Budget row not found.');
			header('Location: ?route=budget-monitor');
			exit;
		}

		$deleted = $budgetModel->deleteBudget($budgetId);
		if (!$deleted) {
			flash_error('Failed to delete budget.');
			header('Location: ?route=budget-monitor');
			exit;
		}

		RbacService::audit('budget_delete', ['budget_id' => $budgetId]);
		flash_success('Budget deleted successfully.');
		header('Location: ?route=budget-monitor');
		exit;
	}
}
