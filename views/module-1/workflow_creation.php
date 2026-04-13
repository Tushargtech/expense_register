<?php
$roles = isset($roles) && is_array($roles) ? $roles : [];
$users = isset($users) && is_array($users) ? $users : [];
$workflow = isset($workflow) && is_array($workflow) ? $workflow : [];
$workflowSteps = isset($workflowSteps) && is_array($workflowSteps) ? $workflowSteps : [];
$formError = isset($formError) ? (string) $formError : '';
$isEdit = isset($isEdit) ? (bool) $isEdit : false;
$formTitle = isset($formTitle) ? (string) $formTitle : ($isEdit ? 'Edit Workflow' : 'Create Workflow');
$formAction = isset($formAction) ? (string) $formAction : '?route=workflows/create';
$submitLabel = isset($submitLabel) ? (string) $submitLabel : ($isEdit ? 'Update Workflow' : 'Save Workflow');
$canEditWorkflow = isset($canEditWorkflow) ? (bool) $canEditWorkflow : true;
$isReadOnlyWorkflow = !$canEditWorkflow;

$workflowName = (string) ($workflow['workflow_name'] ?? '');
$workflowDescription = (string) ($workflow['workflow_description'] ?? '');
$workflowType = (string) ($workflow['workflow_type'] ?? '');
$workflowAmountMin = (string) ($workflow['workflow_amount_min'] ?? '');
$workflowAmountMax = (string) ($workflow['workflow_amount_max'] ?? '');
$workflowIsActive = (int) ($workflow['workflow_is_active'] ?? 1);
$workflowIsDefault = (int) ($workflow['workflow_is_default'] ?? 0) === 1;

if (count($workflowSteps) === 0) {
	$workflowSteps = [[
		'step_order' => 1,
		'step_name' => '',
		'step_approver_type' => 'role',
		'step_approver_role' => '',
		'step_approver_user_id' => 0,
		'step_amount_min' => '',
		'step_amount_max' => '',
		'step_timeout_hours' => '',
		'step_is_required' => true,
	]];
}
?>

<main class="main">
	<div class="page-shell user-create-page">
		<div class="user-create-shell">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<section class="user-create-hero">
				<p class="user-create-kicker">Workflow Management</p>
				<h1 class="user-create-title"><?php echo htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
			</section>

			<?php if ($formError !== ''): ?>
				<div class="alert alert-danger py-2 mb-3" role="alert">
					<?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?>
				</div>
			<?php endif; ?>

			<form method="POST" action="<?php echo htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>" class="user-create-form" id="workflowCreateForm">
				<?php if ($isReadOnlyWorkflow): ?>
				<fieldset disabled>
				<?php endif; ?>
				<section class="user-create-section">
					<div class="user-create-head">
						<div>
							<h2 class="user-create-section-title">Workflow Details</h2>
							<p class="user-create-note">Define workflow details and amount boundaries.</p>
						</div>
					</div>

					<div class="user-create-grid">
						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="workflow_name">Workflow Name</label>
							<input type="text" class="user-create-input" id="workflow_name" name="workflow_name" placeholder="Ex: Procurement Approval" value="<?php echo htmlspecialchars($workflowName, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>

						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="workflow_description">Workflow Description</label>
							<textarea class="user-create-input" id="workflow_description" name="workflow_description" rows="3" placeholder="Describe this workflow..."><?php echo htmlspecialchars($workflowDescription, ENT_QUOTES, 'UTF-8'); ?></textarea>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="workflow_type">Workflow Type</label>
							<select class="user-create-select" id="workflow_type" name="workflow_type" required>
								<option value="">Select Workflow Type</option>
								<option value="Expense" <?php echo $workflowType === 'Expense' ? 'selected' : ''; ?>>Expense</option>
								<option value="Purchase" <?php echo $workflowType === 'Purchase' ? 'selected' : ''; ?>>Purchase</option>
							</select>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="workflow_amount_min">Amount Min</label>
							<input type="number" class="user-create-input" id="workflow_amount_min" name="workflow_amount_min" step="0.01" min="0" placeholder="0.00" value="<?php echo htmlspecialchars($workflowAmountMin, ENT_QUOTES, 'UTF-8'); ?>">
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="workflow_amount_max">Amount Max</label>
							<input type="number" class="user-create-input" id="workflow_amount_max" name="workflow_amount_max" step="0.01" min="0" placeholder="50000.00" value="<?php echo htmlspecialchars($workflowAmountMax, ENT_QUOTES, 'UTF-8'); ?>">
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="workflow_is_active">Status</label>
							<select class="user-create-select" id="workflow_is_active" name="workflow_is_active" required>
									<option value="1" <?php echo $workflowIsActive === 1 ? 'selected' : ''; ?>>Active</option>
									<option value="0" <?php echo $workflowIsActive === 0 ? 'selected' : ''; ?>>Inactive</option>
							</select>
						</div>

						<div class="user-create-field required-slider-field">
							<label class="user-create-label" for="workflow_is_default">Default Workflow</label>
							<label class="required-switch" for="workflow_is_default">
								<input type="checkbox" class="required-toggle" id="workflow_is_default" name="workflow_is_default" value="1" <?php echo $workflowIsDefault ? 'checked' : ''; ?>>
								<span class="required-slider"></span>
								<span class="required-switch-text">Use as default</span>
							</label>
						</div>
					</div>
				</section>

				<section class="user-create-section">
					<div class="user-create-head">
						<div>
							<h2 class="user-create-section-title">Workflow Steps</h2>
							<p class="user-create-note">Drag and drop step cards to reorder approval levels.</p>
						</div>
						<?php if (!$isReadOnlyWorkflow): ?>
						<button type="button" class="user-create-btn user-create-btn-secondary" id="addStepBtn">Add Approval Level</button>
						<?php endif; ?>
					</div>

					<div id="stepsContainer">
						<?php foreach ($workflowSteps as $stepIndex => $step): ?>
						<div class="workflow-step-card workflow-step-row" draggable="true" data-step-index="<?php echo (int) $stepIndex; ?>">
							<div class="workflow-step-card-head">
								<div class="workflow-step-title-wrap">
									<span class="workflow-step-number"><?php echo (int) ($step['step_order'] ?? ($stepIndex + 1)); ?></span>
									<h3 class="workflow-step-title"><?php echo htmlspecialchars((string) ($step['step_name'] ?: 'Approval Step'), ENT_QUOTES, 'UTF-8'); ?></h3>
								</div>
								<div class="workflow-step-actions">
									<button type="button" class="workflow-step-drag-handle" title="Drag to reorder" aria-label="Drag step">::</button>
									<button type="button" class="user-create-btn user-create-btn-secondary remove-step-btn" disabled>Remove</button>
								</div>
							</div>

							<div class="user-create-grid workflow-step-grid">
							<div class="user-create-field">
								<label class="user-create-label">Step Order</label>
								<input type="number" class="user-create-input" name="step_order[]" min="1" value="<?php echo (int) ($step['step_order'] ?? ($stepIndex + 1)); ?>" required>
							</div>

							<div class="user-create-field user-create-field-medium">
								<label class="user-create-label">Step Name</label>
								<input type="text" class="user-create-input step-name-input" name="step_name[]" placeholder="Ex: Manager Approval" value="<?php echo htmlspecialchars((string) ($step['step_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
							</div>

							<div class="user-create-field">
								<label class="user-create-label">Approver Type</label>
								<select class="user-create-select approver-type-select" name="step_approver_type[]">
									<option value="role" <?php echo (string) ($step['step_approver_type'] ?? 'role') === 'role' ? 'selected' : ''; ?>>Role</option>
									<option value="user" <?php echo (string) ($step['step_approver_type'] ?? '') === 'user' ? 'selected' : ''; ?>>User</option>
									<option value="department_head" <?php echo (string) ($step['step_approver_type'] ?? '') === 'department_head' ? 'selected' : ''; ?>>Department Head</option>
								</select>
							</div>

							<div class="user-create-field">
								<label class="user-create-label">Approver Role</label>
								<select class="user-create-select approver-role-select" name="step_approver_role[]">
									<option value="">Select Role</option>
									<?php foreach ($roles as $role): ?>
										<option value="<?php echo htmlspecialchars((string) ($role['role_slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" <?php echo (string) ($step['step_approver_role'] ?? '') === (string) ($role['role_slug'] ?? '') ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars((string) ($role['role_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="user-create-field">
								<label class="user-create-label">Approver User</label>
								<select class="user-create-select approver-user-select" name="step_approver_user_id[]">
									<option value="">Select User</option>
									<?php foreach ($users as $user): ?>
										<?php $userId = (int) ($user['user_id'] ?? 0); ?>
										<option value="<?php echo $userId; ?>" <?php echo (int) ($step['step_approver_user_id'] ?? 0) === $userId ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars((string) ($user['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="user-create-field">
								<label class="user-create-label">Step Amount Min</label>
								<input type="number" class="user-create-input" name="step_amount_min[]" min="0" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars((string) ($step['step_amount_min'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
							</div>

							<div class="user-create-field">
								<label class="user-create-label">Step Amount Max</label>
								<input type="number" class="user-create-input" name="step_amount_max[]" min="0" step="0.01" placeholder="50000.00" value="<?php echo htmlspecialchars((string) ($step['step_amount_max'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
							</div>

							<div class="user-create-field">
								<label class="user-create-label">Timeout (Hours)</label>
								<input type="number" class="user-create-input" name="step_timeout_hours[]" min="1" step="1" placeholder="24" value="<?php echo htmlspecialchars((string) ($step['step_timeout_hours'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
							</div>

							<div class="user-create-field required-slider-field">
								<label class="user-create-label">Required</label>
								<label class="required-switch">
									<input type="checkbox" class="required-toggle" <?php echo !empty($step['step_is_required']) ? 'checked' : ''; ?>>
									<span class="required-slider"></span>
									<span class="required-switch-text">Required Step</span>
								</label>
								<input type="hidden" class="required-hidden-input" name="step_is_required[]" value="<?php echo !empty($step['step_is_required']) ? '1' : '0'; ?>">
							</div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
				</section>
				<?php if ($isReadOnlyWorkflow): ?>
				</fieldset>
				<?php endif; ?>

				<div class="user-create-action-bar">
					<div class="user-create-action-copy">
						<strong>Review workflow details before saving</strong>
						<span>At least one step is required.</span>
					</div>
					<div class="user-create-actions">
						<a href="?route=workflows" class="user-create-btn user-create-btn-secondary">Back to Workflow List</a>
						<?php if (!$isReadOnlyWorkflow): ?>
						<button type="submit" class="user-create-btn user-create-btn-primary"><?php echo htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8'); ?></button>
						<?php endif; ?>
					</div>
				</div>
			</form>
		</div>
	</div>
</main>

<script>
(function () {
	const container = document.getElementById('stepsContainer');
	const addBtn = document.getElementById('addStepBtn');
	if (!container || !addBtn) {
		return;
	}

	const template = container.querySelector('.workflow-step-row');
	if (!template) {
		return;
	}

	const syncStepMeta = function () {
		const rows = container.querySelectorAll('.workflow-step-row');
		rows.forEach(function (row, index) {
			const orderInput = row.querySelector('input[name="step_order[]"]');
			if (orderInput) {
				orderInput.value = String(index + 1);
			}
			const badge = row.querySelector('.workflow-step-number');
			if (badge) {
				badge.textContent = String(index + 1);
			}
		});
	};

	const syncApproverInputs = function (row) {
		const typeSelect = row.querySelector('.approver-type-select');
		const roleSelect = row.querySelector('.approver-role-select');
		const userSelect = row.querySelector('.approver-user-select');
		if (!typeSelect || !roleSelect || !userSelect) {
			return;
		}

		const approverType = typeSelect.value;
		if (approverType === 'role') {
			roleSelect.required = true;
			roleSelect.disabled = false;
			userSelect.required = false;
			userSelect.disabled = true;
			userSelect.value = '';
		} else if (approverType === 'user') {
			roleSelect.required = false;
			roleSelect.disabled = true;
			roleSelect.value = '';
			userSelect.required = true;
			userSelect.disabled = false;
		} else {
			roleSelect.required = false;
			roleSelect.disabled = true;
			roleSelect.value = '';
			userSelect.required = false;
			userSelect.disabled = true;
			userSelect.value = '';
		}
	};

	const syncAllApproverInputs = function () {
		container.querySelectorAll('.workflow-step-row').forEach(function (row) {
			syncApproverInputs(row);
		});
	};

	const syncRequiredHiddenValues = function () {
		const rows = container.querySelectorAll('.workflow-step-row');
		rows.forEach(function (row) {
			const toggle = row.querySelector('.required-toggle');
			const hiddenInput = row.querySelector('.required-hidden-input');
			if (!toggle || !hiddenInput) {
				return;
			}
			hiddenInput.value = toggle.checked ? '1' : '0';
		});
	};

	const refreshRemoveButtons = function () {
		const rows = container.querySelectorAll('.workflow-step-row');
		rows.forEach(function (row, index) {
			const removeBtn = row.querySelector('.remove-step-btn');
			if (!removeBtn) {
				return;
			}
			removeBtn.disabled = rows.length === 1;
			const title = row.querySelector('.workflow-step-title');
			if (title && title.textContent.trim() === '') {
				title.textContent = 'Approval Step';
			}
		});
		syncStepMeta();
	};

	addBtn.addEventListener('click', function () {
		const rowCount = container.querySelectorAll('.workflow-step-row').length;
		const clone = template.cloneNode(true);
		clone.setAttribute('data-step-index', String(rowCount));

		clone.querySelectorAll('input').forEach(function (input) {
			if (input.name === 'step_order[]') {
				input.value = String(rowCount + 1);
			} else {
				input.value = '';
			}
		});

		clone.querySelectorAll('select').forEach(function (select) {
			select.selectedIndex = 0;
		});

		clone.querySelectorAll('.required-toggle').forEach(function (toggle) {
			toggle.checked = true;
		});
		clone.querySelectorAll('.required-hidden-input').forEach(function (hiddenInput) {
			hiddenInput.value = '1';
		});
		clone.querySelectorAll('.step-name-input').forEach(function (input) {
			input.addEventListener('input', function () {});
		});

		container.appendChild(clone);
		syncApproverInputs(clone);
		syncRequiredHiddenValues();
		refreshRemoveButtons();
	});

	let draggedRow = null;

	const getDragAfterElement = function (y) {
		const draggableRows = Array.from(container.querySelectorAll('.workflow-step-row:not(.dragging)'));
		let closest = { offset: Number.NEGATIVE_INFINITY, element: null };

		draggableRows.forEach(function (row) {
			const box = row.getBoundingClientRect();
			const offset = y - (box.top + box.height / 2);
			if (offset < 0 && offset > closest.offset) {
				closest = { offset: offset, element: row };
			}
		});

		return closest.element;
	};

	container.addEventListener('dragstart', function (event) {
		const row = event.target.closest('.workflow-step-row');
		if (!row) {
			return;
		}
		draggedRow = row;
		row.classList.add('dragging');
	});

	container.addEventListener('dragend', function (event) {
		const row = event.target.closest('.workflow-step-row');
		if (row) {
			row.classList.remove('dragging');
		}
		draggedRow = null;
		refreshRemoveButtons();
	});

	container.addEventListener('dragover', function (event) {
		event.preventDefault();
		if (!draggedRow) {
			return;
		}
		const afterElement = getDragAfterElement(event.clientY);
		if (afterElement === null) {
			container.appendChild(draggedRow);
		} else {
			container.insertBefore(draggedRow, afterElement);
		}
	});

	container.addEventListener('click', function (event) {
		const target = event.target;
		if (!(target instanceof HTMLElement)) {
			return;
		}

		if (!target.classList.contains('remove-step-btn')) {
			return;
		}

		const rows = container.querySelectorAll('.workflow-step-row');
		if (rows.length <= 1) {
			return;
		}

		const row = target.closest('.workflow-step-row');
		if (row) {
			row.remove();
			refreshRemoveButtons();
		}
	});

	container.addEventListener('change', function (event) {
		const target = event.target;
		if (!(target instanceof HTMLElement)) {
			return;
		}

		if (!target.classList.contains('required-toggle')) {
			if (target.classList.contains('approver-type-select')) {
				const row = target.closest('.workflow-step-row');
				if (row) {
					syncApproverInputs(row);
				}
			}
			return;
		}

		syncRequiredHiddenValues();
	});

	const form = document.getElementById('workflowCreateForm');
	if (form) {
		form.addEventListener('submit', function () {
			syncRequiredHiddenValues();
		});
	}

	syncRequiredHiddenValues();
	syncAllApproverInputs();
	refreshRemoveButtons();
})();
</script>
