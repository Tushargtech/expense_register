<?php
$formError = isset($formError) ? (string) $formError : '';
$isEdit = isset($isEdit) ? (bool) $isEdit : false;
$formAction = isset($formAction) ? (string) $formAction : buildCleanRouteUrl('budget-categories/create');
$formTitle = isset($formTitle) ? (string) $formTitle : 'Create Budget Category';
$submitLabel = isset($submitLabel) ? (string) $submitLabel : 'Create Category';
$category = isset($category) && is_array($category) ? $category : [];
$categoryTypeOptions = isset($categoryTypeOptions) && is_array($categoryTypeOptions) ? $categoryTypeOptions : [];

$selectedName = (string) ($category['budget_category_name'] ?? '');
$selectedCode = (string) ($category['budget_category_code'] ?? '');
$selectedType = (string) ($category['budget_category_type'] ?? '');
$selectedDescription = (string) ($category['budget_category_description'] ?? '');
$selectedStatus = (int) ($category['budget_category_is_active'] ?? 1);

if ($categoryTypeOptions === [] && trim($selectedType) !== '') {
	$categoryTypeOptions = [strtolower(trim($selectedType))];
}
?>

<main class="main">
	<div class="page-shell user-create-page">
		<div class="user-create-shell">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<section class="user-create-hero">
				<p class="user-create-kicker">Budget Management</p>
				<h1 class="user-create-title"><?php echo htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
			</section>

			<?php if ($formError !== ''): ?>
				<div class="alert alert-danger py-2 mb-3" role="alert">
					<?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?>
				</div>
			<?php endif; ?>

			<form method="POST" action="<?php echo htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>" class="user-create-form">
				<section class="user-create-section">
					<div class="user-create-head">
						<div>
							<h2 class="user-create-section-title">Budget Category Information</h2>
							<p class="user-create-note">Fill all required fields to continue.</p>
						</div>
					</div>

					<div class="user-create-grid">
						<div class="user-create-field">
							<label class="user-create-label" for="budget_category_code">Category Code</label>
							<input type="text" class="user-create-input" id="budget_category_code" name="budget_category_code" placeholder="CAT001" value="<?php echo htmlspecialchars($selectedCode, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>

						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="budget_category_name">Category Name</label>
							<input type="text" class="user-create-input" id="budget_category_name" name="budget_category_name" placeholder="Enter budget category name" value="<?php echo htmlspecialchars($selectedName, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="budget_category_type">Category Type</label>
							<select class="user-create-select" id="budget_category_type" name="budget_category_type" required>
								<option value="">Select Category Type</option>
								<?php foreach ($categoryTypeOptions as $categoryType): ?>
									<?php $normalizedCategoryType = strtolower(trim((string) $categoryType)); ?>
									<?php if ($normalizedCategoryType === '') { continue; } ?>
									<option value="<?php echo htmlspecialchars($normalizedCategoryType, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strtolower($selectedType) === $normalizedCategoryType ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars(ucfirst($normalizedCategoryType), ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="budget_category_description">Description</label>
							<textarea class="user-create-input" id="budget_category_description" name="budget_category_description" rows="4" placeholder="Enter category description"><?php echo htmlspecialchars($selectedDescription, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="budget_category_is_active">Status</label>
							<select class="user-create-select" id="budget_category_is_active" name="budget_category_is_active" required>
								<option value="1" <?php echo $selectedStatus === 1 ? 'selected' : ''; ?>>Active</option>
								<option value="0" <?php echo $selectedStatus === 0 ? 'selected' : ''; ?>>Inactive</option>
							</select>
						</div>
					</div>
				</section>

				<div class="user-create-action-bar">
					<div class="user-create-action-copy">
						<strong><?php echo $isEdit ? 'Review changes before updating' : 'Review before saving'; ?></strong>
					</div>
					<div class="user-create-actions">
						<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('budget-categories'), ENT_QUOTES, 'UTF-8'); ?>" class="user-create-btn user-create-btn-secondary">Back to Category List</a>
						<button type="submit" class="user-create-btn user-create-btn-primary"><?php echo htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8'); ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
</main>
