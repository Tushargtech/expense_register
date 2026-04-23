<?php
$formError = isset($formError) ? (string) $formError : '';
$formTitle = isset($formTitle) ? (string) $formTitle : 'Create Expense Request';
$formAction = isset($formAction) ? (string) $formAction : buildCleanRouteUrl('expenses/create');
$submitLabel = isset($submitLabel) ? (string) $submitLabel : 'Submit Request';
$oldInput = isset($oldInput) && is_array($oldInput) ? $oldInput : [];

$requestTypes = isset($requestTypes) && is_array($requestTypes) ? $requestTypes : [
	'expense' => 'Expense',
	'purchase' => 'Purchase',
];
$priorityOptions = isset($priorityOptions) && is_array($priorityOptions) ? $priorityOptions : [
	'low' => 'Low',
	'medium' => 'Medium',
	'high' => 'High',
];
$currencyOptions = isset($currencyOptions) && is_array($currencyOptions) ? $currencyOptions : ['INR'];

$budgetCategories = isset($budgetCategories) && is_array($budgetCategories) ? $budgetCategories : [];

$selectedRequestType = strtolower(trim((string) ($oldInput['request_type'] ?? 'expense')));
$selectedRequestType = match ($selectedRequestType) {
	'expense' => 'expense',
	'purchase' => 'purchase',
	default => $selectedRequestType,
};
$selectedReferenceNo = (string) ($oldInput['request_reference_no'] ?? '');
$selectedTitle = (string) ($oldInput['request_title'] ?? '');
$selectedAmount = (string) ($oldInput['request_amount'] ?? '');
$selectedPriority = strtolower(trim((string) ($oldInput['request_priority'] ?? 'low')));
$selectedDescription = (string) ($oldInput['request_description'] ?? '');
$selectedNotes = (string) ($oldInput['request_notes'] ?? '');
$selectedAttachmentTypes = isset($oldInput['attachment_types']) && is_array($oldInput['attachment_types']) ? $oldInput['attachment_types'] : [];
$attachmentMaxSizeMb = isset($attachmentMaxSizeMb) ? max(1, (int) $attachmentMaxSizeMb) : 5;
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
						</div>
					</div>

					<div class="user-create-grid expense-details-grid">
						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="request_reference_no">Reference Number <span class="text-danger">*</span></label>
							<input type="text" class="user-create-input" id="request_reference_no" name="request_reference_no" placeholder="Enter receipt reference number" value="<?php echo htmlspecialchars($selectedReferenceNo, ENT_QUOTES, 'UTF-8'); ?>" maxlength="30" required>
						</div>

						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="request_title">Title <span class="text-danger">*</span></label>
							<input type="text" class="user-create-input" id="request_title" name="request_title" placeholder="Enter request title" value="<?php echo htmlspecialchars($selectedTitle, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_type">Request Type <span class="text-danger">*</span></label>
							<select class="user-create-select" id="request_type" name="request_type" required>
								<?php foreach ($requestTypes as $key => $label): ?>
									<option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedRequestType === $key ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_amount">Amount <span class="text-danger">*</span></label>
							<input type="number" class="user-create-input" id="request_amount" name="request_amount" placeholder="0.00" step="0.01" min="0.01" value="<?php echo htmlspecialchars($selectedAmount, ENT_QUOTES, 'UTF-8'); ?>" required>
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
						</div>
					</div>

					<div class="user-create-grid expense-additional-grid">
						<div class="user-create-field">
							<label class="user-create-label" for="request_description">Description <span class="text-danger">*</span></label>
							<textarea class="user-create-input" id="request_description" name="request_description" rows="4" placeholder="Describe the expense request" required><?php echo htmlspecialchars($selectedDescription, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="request_notes">Notes</label>
							<textarea class="user-create-input" id="request_notes" name="request_notes" rows="4" placeholder="Add any internal notes"><?php echo htmlspecialchars($selectedNotes, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>

						<div id="attachmentsContainer">
							<div class="attachmentGroup" data-attachment-index="0">
								<div class="user-create-field">
									<label class="user-create-label">Attachment Type</label>
									<select class="user-create-select attachmentTypeSelect" name="attachment_type[]">
										<option value="">Select Attachment Type</option>
										<option value="invoice" <?php echo (isset($selectedAttachmentTypes[0]) && $selectedAttachmentTypes[0] === 'invoice') ? 'selected' : ''; ?>>Invoice</option>
										<option value="receipt" <?php echo (isset($selectedAttachmentTypes[0]) && $selectedAttachmentTypes[0] === 'receipt') ? 'selected' : ''; ?>>Receipt</option>
										<option value="other" <?php echo (isset($selectedAttachmentTypes[0]) && $selectedAttachmentTypes[0] === 'other') ? 'selected' : ''; ?>>Others</option>
									</select>
								</div>

								<div class="user-create-field attachmentFileWrap" style="display: none;">
									<label class="user-create-label">Attachment File</label>
									<input
										type="file"
										class="user-create-input attachmentFileInput"
										name="attachment_file[]"
										accept=".pdf,.jpg,.jpeg,.png,.doc"
									>
									<p class="user-create-note">Allowed file types: PDF, JPG, JPEG, PNG, DOC. Max <?php echo (int) $attachmentMaxSizeMb; ?> MB per file.</p>
								</div>

								<div style="display: flex; gap: 10px; margin-top: 10px;">
									<button type="button" class="removeAttachmentBtn user-create-btn user-create-btn-secondary" style="display: none;">Remove Attachment</button>
								</div>
							</div>
							<?php
							if (count($selectedAttachmentTypes) > 1) {
								for ($i = 1; $i < count($selectedAttachmentTypes); $i++) {
									$attachmentType = isset($selectedAttachmentTypes[$i]) ? strtolower(trim((string) $selectedAttachmentTypes[$i])) : '';
									?>
									<div class="attachmentGroup" data-attachment-index="<?php echo (int) $i; ?>">
										<div class="user-create-field">
											<label class="user-create-label">Attachment Type</label>
											<select class="user-create-select attachmentTypeSelect" name="attachment_type[]">
												<option value="">Select Attachment Type</option>
												<option value="invoice" <?php echo $attachmentType === 'invoice' ? 'selected' : ''; ?>>Invoice</option>
												<option value="receipt" <?php echo $attachmentType === 'receipt' ? 'selected' : ''; ?>>Receipt</option>
												<option value="other" <?php echo $attachmentType === 'other' ? 'selected' : ''; ?>>Others</option>
											</select>
										</div>

										<div class="user-create-field attachmentFileWrap" style="display: none;">
											<label class="user-create-label">Attachment File</label>
											<input
												type="file"
												class="user-create-input attachmentFileInput"
												name="attachment_file[]"
												accept=".pdf,.jpg,.jpeg,.png,.doc"
											>
											<p class="user-create-note">Allowed file types: PDF, JPG, JPEG, PNG, DOC. Max <?php echo (int) $attachmentMaxSizeMb; ?> MB per file.</p>
										</div>

										<div style="display: flex; gap: 10px; margin-top: 10px;">
											<button type="button" class="removeAttachmentBtn user-create-btn user-create-btn-secondary">Remove Attachment</button>
										</div>
									</div>
									<?php
								}
							}
							?>
						</div>

						<button type="button" id="addAttachmentBtn" class="user-create-btn user-create-btn-secondary" style="margin-top: 10px;">Add More Attachments</button>
					</div>
				</section>

				<div class="user-create-action-bar">
					<div class="user-create-action-copy">
						<strong>Review before submitting</strong>
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
	if (!requestTypeSelect) {
		return;
	}

const attachmentsContainer = document.getElementById('attachmentsContainer');
	const addAttachmentBtn = document.getElementById('addAttachmentBtn');

	const syncAttachmentGroupVisibility = function (group) {
		const typeSelect = group.querySelector('.attachmentTypeSelect');
		const fileWrap = group.querySelector('.attachmentFileWrap');
		const fileInput = group.querySelector('.attachmentFileInput');
		const removeBtn = group.querySelector('.removeAttachmentBtn');

		if (!typeSelect || !fileWrap || !fileInput) {
			return;
		}

		const hasAttachmentType = String(typeSelect.value || '').trim() !== '';
		fileWrap.style.display = hasAttachmentType ? '' : 'none';
		if (!hasAttachmentType) {
			fileInput.value = '';
		}

		// Show remove button only if there are multiple attachment groups
		if (removeBtn) {
			const groupCount = attachmentsContainer.querySelectorAll('.attachmentGroup').length;
			removeBtn.style.display = groupCount > 1 ? '' : 'none';
		}
	};

	const attachTypeChangeHandler = function (event) {
		const group = event.target.closest('.attachmentGroup');
		if (group) {
			syncAttachmentGroupVisibility(group);
		}
	};

	const removeAttachmentHandler = function (event) {
		event.preventDefault();
		const group = event.target.closest('.attachmentGroup');
		if (group) {
			group.remove();
			// Update visibility of remaining remove buttons
			attachmentsContainer.querySelectorAll('.attachmentGroup').forEach(function (g) {
				syncAttachmentGroupVisibility(g);
			});
		}
	};

	const addAttachmentHandler = function (event) {
		event.preventDefault();
		const firstGroup = attachmentsContainer.querySelector('.attachmentGroup');
		if (!firstGroup) {
			return;
		}

		const newGroup = firstGroup.cloneNode(true);
		const newIndex = attachmentsContainer.querySelectorAll('.attachmentGroup').length;
		newGroup.setAttribute('data-attachment-index', newIndex);

		// Clear the values
		newGroup.querySelector('.attachmentTypeSelect').value = '';
		newGroup.querySelector('.attachmentFileInput').value = '';

		// Reset event listeners
		const typeSelect = newGroup.querySelector('.attachmentTypeSelect');
		if (typeSelect) {
			typeSelect.removeEventListener('change', attachTypeChangeHandler);
			typeSelect.addEventListener('change', attachTypeChangeHandler);
		}

		const removeBtn = newGroup.querySelector('.removeAttachmentBtn');
		if (removeBtn) {
			removeBtn.removeEventListener('click', removeAttachmentHandler);
			removeBtn.addEventListener('click', removeAttachmentHandler);
		}

		attachmentsContainer.appendChild(newGroup);
		syncAttachmentGroupVisibility(newGroup);

		// Update visibility of remove buttons
		attachmentsContainer.querySelectorAll('.attachmentGroup').forEach(function (g) {
			syncAttachmentGroupVisibility(g);
		});
	};

	// Initialize event listeners for existing groups
	attachmentsContainer.querySelectorAll('.attachmentGroup').forEach(function (group) {
		const typeSelect = group.querySelector('.attachmentTypeSelect');
		const removeBtn = group.querySelector('.removeAttachmentBtn');

		if (typeSelect) {
			typeSelect.addEventListener('change', attachTypeChangeHandler);
		}

		if (removeBtn) {
			removeBtn.addEventListener('click', removeAttachmentHandler);
		}

		syncAttachmentGroupVisibility(group);
	});

	if (addAttachmentBtn) {
		addAttachmentBtn.addEventListener('click', addAttachmentHandler);
	}
})();
</script>