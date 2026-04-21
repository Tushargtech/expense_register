<?php
$formError = isset($formError) ? (string) $formError : '';
$formTitle = isset($formTitle) ? (string) $formTitle : 'Create Expense Request';
$formAction = isset($formAction) ? (string) $formAction : buildCleanRouteUrl('expenses/create');
$submitLabel = isset($submitLabel) ? (string) $submitLabel : 'Submit Request';
$oldInput = isset($oldInput) && is_array($oldInput) ? $oldInput : [];

$requestTypes = isset($requestTypes) && is_array($requestTypes) ? $requestTypes : [
	'reimbursable' => 'Reimbursable',
    'company paid' => 'Company Paid',
];
$priorityOptions = isset($priorityOptions) && is_array($priorityOptions) ? $priorityOptions : [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
];
$currencyOptions = isset($currencyOptions) && is_array($currencyOptions) ? $currencyOptions : ['INR'];

$budgetCategories = isset($budgetCategories) && is_array($budgetCategories) ? $budgetCategories : [];

$selectedRequestType = strtolower(trim((string) ($oldInput['request_type'] ?? 'reimbursable')));
$selectedTitle = (string) ($oldInput['request_title'] ?? '');
$selectedAmount = (string) ($oldInput['request_amount'] ?? '');
$selectedCurrency = strtoupper(trim((string) ($oldInput['request_currency'] ?? (string) ($currencyOptions[0] ?? 'INR'))));

$selectedBudgetCategoryId = (int) ($oldInput['budget_category_id'] ?? 0);
$selectedPriority = strtolower(trim((string) ($oldInput['request_priority'] ?? 'low')));
$selectedDescription = (string) ($oldInput['request_description'] ?? '');
$selectedNotes = (string) ($oldInput['request_notes'] ?? '');
?>

<main class="main">
	<div class="page-shell user-create-page expense-create-page">
		<div class="user-create-shell">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<section class="user-create-hero">
				<p class="user-create-kicker">Expense Management</p>
				<h1 class="user-create-title"><?php echo htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
			</section>

			<?php if ($formError !== ''): ?>
				<div class="alert alert-danger py-2 mb-3" role="alert">
					<?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?>
				</div>
			<?php endif; ?>

			<form method="POST" action="<?php echo htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>" enctype="multipart/form-data" class="user-create-form expense-create-form">
				<section class="user-create-section expense-create-left-section">
					<div class="user-create-head">
						<div>
							<h2 class="user-create-section-title">Request Details</h2>
							<p class="user-create-note">Fill in the request details below.</p>
						</div>
					</div>

					<div class="user-create-grid expense-details-grid">
						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="request_title">Title</label>
							<input type="text" class="user-create-input" id="request_title" name="request_title" placeholder="Enter request title" value="<?php echo htmlspecialchars($selectedTitle, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_type">Request Type</label>
							<select class="user-create-select" id="request_type" name="request_type" required>
								<?php foreach ($requestTypes as $key => $label): ?>
									<option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedRequestType === $key ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_amount">Amount</label>
							<input type="number" class="user-create-input" id="request_amount" name="request_amount" placeholder="0.00" step="0.01" min="0.01" value="<?php echo htmlspecialchars($selectedAmount, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_currency">Currency</label>
							<select class="user-create-select" id="request_currency" name="request_currency" required>
								<?php foreach ($currencyOptions as $currency): ?>
									<?php $currencyValue = strtoupper(trim((string) $currency)); ?>
									<?php if ($currencyValue === '') { continue; } ?>
									<option value="<?php echo htmlspecialchars($currencyValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedCurrency === $currencyValue ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($currencyValue, ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					

						<div class="user-create-field">
							<label class="user-create-label" for="budget_category_id">Budget Category</label>
							<select class="user-create-select" id="budget_category_id" name="budget_category_id" required>
								<option value="">Select Budget Category</option>
								<?php foreach ($budgetCategories as $category): ?>
									<?php
									$categoryId = (int) ($category['budget_category_id'] ?? 0);
									$categoryTypeRaw = strtolower(trim((string) ($category['budget_category_type'] ?? '')));
									$categoryType = match ($categoryTypeRaw) {
										'expense' => 'reimbursable',
										'purchase', 'company_paid' => 'company paid',
										default => $categoryTypeRaw,
									};
									?>
									<option
										value="<?php echo $categoryId; ?>"
										data-category-type="<?php echo htmlspecialchars($categoryType, ENT_QUOTES, 'UTF-8'); ?>"
										<?php echo $selectedBudgetCategoryId === $categoryId ? 'selected' : ''; ?>
									>
										<?php echo htmlspecialchars((string) ($category['budget_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="user-create-note">Categories are filtered by request type.</p>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_priority">Priority</label>
							<select class="user-create-select" id="request_priority" name="request_priority" required>
								<?php foreach ($priorityOptions as $key => $label): ?>
									<option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedPriority === $key ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</section>

				<section class="user-create-section expense-create-right-section">
					<div class="user-create-head">
						<div>
							<h2 class="user-create-section-title">Additional Information</h2>
							<p class="user-create-note">Add context, notes, and an optional attachment.</p>
						</div>
					</div>

					<div class="user-create-grid expense-additional-grid">
						<div class="user-create-field">
							<label class="user-create-label" for="request_description">Description</label>
							<textarea class="user-create-input" id="request_description" name="request_description" rows="4" placeholder="Describe the expense request"><?php echo htmlspecialchars($selectedDescription, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_notes">Notes</label>
							<textarea class="user-create-input" id="request_notes" name="request_notes" rows="4" placeholder="Add any internal notes"><?php echo htmlspecialchars($selectedNotes, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="attachment_file">Attachment</label>
							<input
								type="file"
								class="user-create-input"
								id="attachment_file"
								name="attachment_file"
								accept=".pdf,.jpg,.jpeg,.png,.doc"
							>
							<p class="user-create-note">Allowed file types: PDF, JPG, JPEG, PNG, DOC.</p>
						</div>
					</div>
				</section>

				<div class="user-create-action-bar">
					<div class="user-create-action-copy">
						<strong>Review before submitting</strong>
						<span>The request will start in pending status.</span>
					</div>
					<div class="user-create-actions">
						<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses'), ENT_QUOTES, 'UTF-8'); ?>" class="user-create-btn user-create-btn-secondary">Back to Expense List</a>
						<button type="submit" class="user-create-btn user-create-btn-primary"><?php echo htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8'); ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
</main>

<script>
(function () {
	const requestTypeSelect = document.getElementById('request_type');
	const budgetCategorySelect = document.getElementById('budget_category_id');
	if (!requestTypeSelect || !budgetCategorySelect) {
		return;
	}

	const syncBudgetCategories = function () {
		const normalizeType = function (value) {
			const normalized = String(value || '').toLowerCase().trim();
			if (normalized === 'expense') {
				return 'reimbursable';
			}
			if (normalized === 'purchase' || normalized === 'company_paid') {
				return 'company paid';
			}
			return normalized;
		};

		const requestType = normalizeType(requestTypeSelect.value || '');
		const options = budgetCategorySelect.querySelectorAll('option[data-category-type]');
		let selectedOptionVisible = false;

		options.forEach(function (option) {
			const categoryType = normalizeType(option.getAttribute('data-category-type') || '');
			const shouldShow = requestType === '' || categoryType === '' || categoryType === requestType;
			option.hidden = !shouldShow;
			option.disabled = !shouldShow;
			if (option.selected && shouldShow) {
				selectedOptionVisible = true;
			}
		});

		if (!selectedOptionVisible) {
			budgetCategorySelect.value = '';
		}
	};

	requestTypeSelect.addEventListener('change', syncBudgetCategories);
	syncBudgetCategories();
})();
</script>