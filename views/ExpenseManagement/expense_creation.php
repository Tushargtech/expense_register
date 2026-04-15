<?php
$departments = isset($departments) && is_array($departments) ? $departments : [];
$categories = isset($categories) && is_array($categories) ? $categories : [];
$formAction = isset($formAction) ? (string) $formAction : buildCleanRouteUrl('expenses/create');
$formTitle = isset($formTitle) ? (string) $formTitle : 'Create Expense Request';
$submitLabel = isset($submitLabel) ? (string) $submitLabel : 'Submit Request';
$expense = isset($expense) && is_array($expense) ? $expense : [];
$isReadOnly = isset($isReadOnly) ? (bool) $isReadOnly : false;
$requestAttachments = isset($requestAttachments) && is_array($requestAttachments) ? $requestAttachments : [];
$requestTypeOptions = isset($requestTypeOptions) && is_array($requestTypeOptions) ? $requestTypeOptions : [];
$requestCurrencyOptions = isset($requestCurrencyOptions) && is_array($requestCurrencyOptions) ? $requestCurrencyOptions : [];
$requestPriorityOptions = isset($requestPriorityOptions) && is_array($requestPriorityOptions) ? $requestPriorityOptions : [];

$selectedTitle = (string) ($expense['request_title'] ?? '');
$selectedType = (string) ($expense['request_type'] ?? 'expense');
$selectedDescription = (string) ($expense['request_description'] ?? '');
$selectedAmount = (string) ($expense['request_amount'] ?? '');
$selectedCurrency = (string) ($expense['request_currency'] ?? 'INR');
$selectedDepartmentId = (int) ($expense['department_id'] ?? 0);
$selectedBudgetCategoryId = (int) ($expense['budget_category_id'] ?? 0);
$selectedPriority = (string) ($expense['request_priority'] ?? 'medium');
$selectedNotes = (string) ($expense['request_notes'] ?? '');

if ($requestCurrencyOptions === [] && trim($selectedCurrency) !== '') {
	$requestCurrencyOptions = [strtoupper(trim($selectedCurrency))];
}
if ($requestTypeOptions === [] && trim($selectedType) !== '') {
	$requestTypeOptions = [strtolower(trim($selectedType))];
}
if ($requestPriorityOptions === [] && trim($selectedPriority) !== '') {
	$requestPriorityOptions = [strtolower(trim($selectedPriority))];
}
?>

<main class="main">
	<div class="page-shell user-create-page">
		<div class="user-create-shell">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<section class="user-create-hero">
				<p class="user-create-kicker">Expense Management</p>
				<h1 class="user-create-title"><?php echo htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
			</section>

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
							<input type="text" class="user-create-input" id="request_title" name="request_title" placeholder="Enter expense title" value="<?php echo htmlspecialchars($selectedTitle, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isReadOnly ? 'readonly' : 'required'; ?>>
						</div>
						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="request_type">Request Type</label>
							<select class="user-create-select" id="request_type" name="request_type" <?php echo $isReadOnly ? 'disabled' : 'required'; ?>>
								<?php foreach ($requestTypeOptions as $requestTypeOption): ?>
									<?php $normalizedRequestType = strtolower(trim((string) $requestTypeOption)); ?>
									<?php if ($normalizedRequestType === '') { continue; } ?>
									<option value="<?php echo htmlspecialchars($normalizedRequestType, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strtolower($selectedType) === $normalizedRequestType ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars(ucfirst($normalizedRequestType), ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_amount">Amount</label>
							<input type="number" step="0.01" min="0.01" class="user-create-input" id="request_amount" name="request_amount" placeholder="0.00" value="<?php echo htmlspecialchars($selectedAmount, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isReadOnly ? 'readonly' : 'required'; ?>>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_currency">Currency</label>
							<select class="user-create-select" id="request_currency" name="request_currency" <?php echo $isReadOnly ? 'disabled' : 'required'; ?>>
								<?php foreach ($requestCurrencyOptions as $requestCurrencyOption): ?>
									<?php $normalizedRequestCurrency = strtoupper(trim((string) $requestCurrencyOption)); ?>
									<?php if ($normalizedRequestCurrency === '') { continue; } ?>
									<option value="<?php echo htmlspecialchars($normalizedRequestCurrency, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strtoupper($selectedCurrency) === $normalizedRequestCurrency ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($normalizedRequestCurrency, ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="department_id">Department</label>
							<select class="user-create-select" id="department_id" name="department_id" <?php echo $isReadOnly ? 'disabled' : 'required'; ?>>
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
							<select class="user-create-select" id="budget_category_id" name="budget_category_id" <?php echo $isReadOnly ? 'disabled' : 'required'; ?>>
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
							<select class="user-create-select" id="request_priority" name="request_priority" <?php echo $isReadOnly ? 'disabled' : 'required'; ?>>
								<?php foreach ($requestPriorityOptions as $requestPriorityOption): ?>
									<?php $normalizedRequestPriority = strtolower(trim((string) $requestPriorityOption)); ?>
									<?php if ($normalizedRequestPriority === '') { continue; } ?>
									<option value="<?php echo htmlspecialchars($normalizedRequestPriority, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strtolower($selectedPriority) === $normalizedRequestPriority ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars(ucfirst($normalizedRequestPriority), ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="request_description">Description</label>
							<textarea class="user-create-input" id="request_description" name="request_description" rows="4" placeholder="Enter expense details" <?php echo $isReadOnly ? 'readonly' : ''; ?>><?php echo htmlspecialchars($selectedDescription, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>

						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="request_notes">Notes</label>
							<textarea class="user-create-input" id="request_notes" name="request_notes" rows="4" placeholder="Additional notes" <?php echo $isReadOnly ? 'readonly' : ''; ?>><?php echo htmlspecialchars($selectedNotes, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>

						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="attachment_file">Attachment</label>
							<?php if ($isReadOnly): ?>
								<div class="form-control bg-light">Attachments are shown below in read-only mode.</div>
							<?php else: ?>
								<input type="file" class="user-create-input" id="attachment_file" name="attachment_file" accept=".pdf,.jpg,.jpeg,.png">
								<small class="user-create-note">Allowed: PDF, JPG, JPEG, PNG</small>
							<?php endif; ?>
						</div>
					</div>
				</section>

				<?php if ($isReadOnly && !empty($requestAttachments)): ?>
					<section class="user-create-section">
						<div class="user-create-head">
							<div>
								<h2 class="user-create-section-title">Attachment Details</h2>
							</div>
						</div>
						<div class="table-responsive">
							<table class="table align-middle mb-0">
								<thead>
									<tr>
										<th>File Name</th>
										<th>Type</th>
										<th>Size</th>
										<th>MIME</th>
										<th class="text-end">Action</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($requestAttachments as $attachment): ?>
										<?php $isAttachmentAvailable = (bool) ($attachment['is_available'] ?? false); ?>
										<tr>
											<td><?php echo htmlspecialchars((string) ($attachment['attachment_file_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars((string) ($attachment['attachment_type'] ?? 'other'), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo number_format(((int) ($attachment['attachment_file_size'] ?? 0)) / 1024, 2); ?> KB</td>
											<td><?php echo htmlspecialchars((string) ($attachment['attachment_mime_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
											<td class="text-end">
												<?php if ($isAttachmentAvailable): ?>
													<a class="btn btn-sm btn-outline-secondary action-icon-btn" target="_blank" rel="noopener" href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses/attachment/view', ['request_id' => (int) ($expense['request_id'] ?? 0), 'attachment_id' => (int) ($attachment['attachment_id'] ?? 0)]), ENT_QUOTES, 'UTF-8'); ?>" title="View" aria-label="View attachment">
														<i class="bi bi-eye" aria-hidden="true"></i>
													</a>
													<a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses/attachment/download', ['request_id' => (int) ($expense['request_id'] ?? 0), 'attachment_id' => (int) ($attachment['attachment_id'] ?? 0)]), ENT_QUOTES, 'UTF-8'); ?>">Download</a>
												<?php else: ?>
													<button type="button" class="btn btn-sm btn-outline-secondary action-icon-btn" disabled title="View" aria-label="View attachment unavailable">
														<i class="bi bi-eye" aria-hidden="true"></i>
													</button>
													<button type="button" class="btn btn-sm btn-primary" disabled>Download</button>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</section>
				<?php endif; ?>

				<div class="user-create-action-bar">
					<div class="user-create-action-copy">
						<strong><?php echo $isReadOnly ? 'View only mode' : 'Review before submitting'; ?></strong>
					</div>
					<div class="user-create-actions">
						<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('expenses'), ENT_QUOTES, 'UTF-8'); ?>" class="user-create-btn user-create-btn-secondary">Back to Expense List</a>
						<?php if (!$isReadOnly): ?>
							<button type="submit" class="user-create-btn user-create-btn-primary"><?php echo htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8'); ?></button>
						<?php endif; ?>
					</div>
				</div>

					<?php if (!$isReadOnly): ?>
						<input type="hidden" name="workflow_id" value="0">
					<?php endif; ?>
			</form>
		</div>
	</div>
</main>
