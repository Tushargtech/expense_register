<?php
$departments = isset($departments) && is_array($departments) ? $departments : [];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$formError = isset($formError) ? (string) $formError : '';
$formAction = isset($formAction) ? (string) $formAction : '?route=expenses/create';
$formTitle = isset($formTitle) ? (string) $formTitle : 'Create Expense Request';
$submitLabel = isset($submitLabel) ? (string) $submitLabel : 'Submit Request';
$expense = isset($expense) && is_array($expense) ? $expense : [];

$selectedTitle = (string) ($expense['request_title'] ?? '');
$selectedDescription = (string) ($expense['request_description'] ?? '');
$selectedAmount = (string) ($expense['request_amount'] ?? '');
$selectedCurrency = (string) ($expense['request_currency'] ?? 'INR');
$selectedDepartmentId = (int) ($expense['department_id'] ?? 0);
$selectedBudgetCategoryId = (int) ($expense['budget_category_id'] ?? 0);
$selectedPriority = (string) ($expense['request_priority'] ?? 'medium');
$selectedNotes = (string) ($expense['request_notes'] ?? '');
?>

<main class="main">
	<div class="page-shell user-create-page">
		<div class="user-create-shell">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<section class="user-create-hero">
				<p class="user-create-kicker">Expense Request</p>
				<h1 class="user-create-title"><?php echo htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
			</section>

			<?php if ($formError !== ''): ?>
				<div class="alert alert-danger py-2 mb-3" role="alert">
					<?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?>
				</div>
			<?php endif; ?>

			<form method="POST" action="<?php echo htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>" class="user-create-form" enctype="multipart/form-data">
				<section class="user-create-section">
					<div class="user-create-head">
						<div>
							<h2 class="user-create-section-title">Expense Information</h2>
							<p class="user-create-note">Fill all required fields to continue.</p>
						</div>
					</div>

					<div class="user-create-grid">
						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="request_title">Title</label>
							<input type="text" class="user-create-input" id="request_title" name="request_title" placeholder="Enter expense title" value="<?php echo htmlspecialchars($selectedTitle, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_amount">Amount</label>
							<input type="number" step="0.01" min="0.01" class="user-create-input" id="request_amount" name="request_amount" placeholder="0.00" value="<?php echo htmlspecialchars($selectedAmount, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_currency">Currency</label>
							<input type="text" maxlength="3" pattern="[A-Za-z]{3}" class="user-create-input" id="request_currency" name="request_currency" value="<?php echo htmlspecialchars($selectedCurrency, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="department_id">Department</label>
							<select class="user-create-select" id="department_id" name="department_id" required>
								<option value="">Select Department</option>
								<?php foreach ($departments as $department): ?>
									<?php $departmentId = (int) ($department['id'] ?? 0); ?>
									<option value="<?php echo $departmentId; ?>" <?php echo $selectedDepartmentId === $departmentId ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars((string) ($department['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="budget_category_id">Budget Category</label>
							<select class="user-create-select" id="budget_category_id" name="budget_category_id" required>
								<option value="">Select Category</option>
								<?php foreach ($categories as $category): ?>
									<?php $categoryId = (int) ($category['budget_category_id'] ?? 0); ?>
									<option value="<?php echo $categoryId; ?>" <?php echo $selectedBudgetCategoryId === $categoryId ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars((string) ($category['budget_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_priority">Priority</label>
							<select class="user-create-select" id="request_priority" name="request_priority" required>
								<option value="low" <?php echo $selectedPriority === 'low' ? 'selected' : ''; ?>>Low</option>
								<option value="medium" <?php echo $selectedPriority === 'medium' ? 'selected' : ''; ?>>Medium</option>
								<option value="high" <?php echo $selectedPriority === 'high' ? 'selected' : ''; ?>>High</option>
							</select>
						</div>

						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="request_description">Description</label>
							<textarea class="user-create-input" id="request_description" name="request_description" rows="4" placeholder="Enter expense details"><?php echo htmlspecialchars($selectedDescription, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>

						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="request_notes">Notes</label>
							<textarea class="user-create-input" id="request_notes" name="request_notes" rows="4" placeholder="Additional notes"><?php echo htmlspecialchars($selectedNotes, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>

						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="attachment_file">Attachment</label>
							<input type="file" class="user-create-input" id="attachment_file" name="attachment_file" accept=".pdf,.jpg,.jpeg,.png">
							<small class="user-create-note">Allowed: PDF, JPG, JPEG, PNG</small>
						</div>
					</div>
				</section>

				<div class="user-create-action-bar">
					<div class="user-create-action-copy">
						<strong>Review before submitting</strong>
					</div>
					<div class="user-create-actions">
						<a href="?route=expenses" class="user-create-btn user-create-btn-secondary">Back to Expense List</a>
						<button type="submit" class="user-create-btn user-create-btn-primary"><?php echo htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8'); ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
</main>
