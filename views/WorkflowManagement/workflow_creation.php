<?php
$roles = isset($roles) && is_array($roles) ? $roles : [];
$users = isset($users) && is_array($users) ? $users : [];
$workflow = isset($workflow) && is_array($workflow) ? $workflow : [];
$workflowSteps = isset($workflowSteps) && is_array($workflowSteps) ? $workflowSteps : [];
$formError = isset($formError) ? (string) $formError : '';
$isEdit = isset($isEdit) ? (bool) $isEdit : false;
$formTitle = isset($formTitle) ? (string) $formTitle : ($isEdit ? 'Edit Workflow' : 'Create Workflow');
$formAction = isset($formAction) ? (string) $formAction : buildCleanRouteUrl('workflows/create');
$submitLabel = isset($submitLabel) ? (string) $submitLabel : ($isEdit ? 'Update Workflow' : 'Save Workflow');
$canEditWorkflow = isset($canEditWorkflow) ? (bool) $canEditWorkflow : true;
$isReadOnlyWorkflow = !$canEditWorkflow;
$workflowTypeOptions = isset($workflowTypeOptions) && is_array($workflowTypeOptions) ? $workflowTypeOptions : [];
$budgetCategories = isset($budgetCategories) && is_array($budgetCategories) ? $budgetCategories : [];

$workflowName = (string) ($workflow['workflow_name'] ?? '');
$workflowDescription = (string) ($workflow['workflow_description'] ?? '');
$workflowType = (string) ($workflow['workflow_type'] ?? '');
$selectedBudgetCategoryId = (int) ($workflow['budget_category_id'] ?? 0);
$workflowAmountMin = (string) ($workflow['workflow_amount_min'] ?? '');
$workflowAmountMax = (string) ($workflow['workflow_amount_max'] ?? '');
$workflowIsActive = (int) ($workflow['workflow_is_active'] ?? 1);
$workflowIsDefault = (int) ($workflow['workflow_is_default'] ?? 0) === 1;

if ($workflowTypeOptions === [] && trim($workflowType) !== '') {
	$workflowTypeOptions = [ucfirst(strtolower(trim($workflowType)))];
}

if (count($workflowSteps) === 0) {
	$workflowSteps = [[
		'step_order' => 1,
		'step_name' => '',
		'step_approver_type' => 'role',
		'step_approver_role' => '',
		'step_approver_user_id' => 0,
		'step_timeout_hours' => '',
		'step_is_required' => true,
	]];
}

$workflowTypeLabels = [
	'reimbursable' => 'Reimbursable',
	'company paid' => 'Company Paid',
];
?>

<main class="main">
	<div class="page-shell user-create-page workflow-create-page">
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

			<form method="POST" action="<?php echo htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'); ?>" class="user-create-form workflow-create-form" id="workflowCreateForm">
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
							<label class="user-create-label" for="budget_category_id">Workflow Category</label>
							<select class="user-create-select" id="budget_category_id" name="budget_category_id" required>
								<option value="">Select Workflow Category</option>
								<?php foreach ($budgetCategories as $category): ?>
									<?php $categoryId = (int) ($category['budget_category_id'] ?? 0); ?>
									<option value="<?php echo $categoryId; ?>" <?php echo $selectedBudgetCategoryId === $categoryId ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars((string) ($category['budget_category_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="workflow_type">Workflow Type</label>
							<select class="user-create-select" id="workflow_type" name="workflow_type" required>
								<option value="">Select Workflow Type</option>
								<?php foreach ($workflowTypeOptions as $workflowTypeOption): ?>
									<?php $normalizedWorkflowType = ucfirst(strtolower(trim((string) $workflowTypeOption))); ?>
									<?php if ($normalizedWorkflowType === '') { continue; } ?>
									<option value="<?php echo htmlspecialchars($normalizedWorkflowType, ENT_QUOTES, 'UTF-8'); ?>" <?php echo strtolower($workflowType) === strtolower($normalizedWorkflowType) ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($workflowTypeLabels[strtolower($normalizedWorkflowType)] ?? $normalizedWorkflowType, ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
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
						<button type="button" class="user-create-btn user-create-btn-secondary" id="addStepBtn">Add Approval Step</button>
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
								<input type="hidden" name="step_id[]" value="<?php echo (int) ($step['step_id'] ?? 0); ?>">
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
									<option value="manager" <?php echo (string) ($step['step_approver_type'] ?? '') === 'manager' ? 'selected' : ''; ?>>Manager</option>
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
									<option value="">Any user in selected role</option>
									<?php foreach ($users as $user): ?>
										<?php
										$userId = (int) ($user['user_id'] ?? 0);
										$userRole = strtolower(trim((string) ($user['approver_role'] ?? '')));
										?>
										<option value="<?php echo $userId; ?>" data-user-role="<?php echo htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (int) ($step['step_approver_user_id'] ?? 0) === $userId ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars((string) ($user['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="user-create-note">Optional: leave blank to assign this step to all users in the selected role.</p>
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
						<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('workflows'), ENT_QUOTES, 'UTF-8'); ?>" class="user-create-btn user-create-btn-secondary">Back to Workflow List</a>
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
		if (!typeSelect || !roleSelect) {
			return;
		}

		const approverType = typeSelect.value;
		let targetRole = roleSelect.value;
		if (approverType === 'role') {
			roleSelect.required = true;
			roleSelect.disabled = false;
			targetRole = roleSelect.value;
		} else if (approverType === 'manager') {
			roleSelect.required = false;
			roleSelect.disabled = true;
			roleSelect.value = 'manager';
			targetRole = 'manager';
		} else {
			roleSelect.required = false;
			roleSelect.disabled = true;
			roleSelect.value = 'department_head';
			targetRole = 'department_head';
		}

		if (userSelect) {
			let visibleSelection = false;
			userSelect.querySelectorAll('option[data-user-role]').forEach(function (option) {
				const optionRole = String(option.getAttribute('data-user-role') || '').toLowerCase();
				const shouldShow = targetRole !== '' && optionRole === targetRole;
				option.hidden = !shouldShow;
				option.disabled = !shouldShow;
				if (option.selected && shouldShow) {
					visibleSelection = true;
				}
			});
			userSelect.required = false;
			if (!visibleSelection) {
				userSelect.value = '';
			}
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

		const approverTypeSelect = clone.querySelector('.approver-type-select');
		if (approverTypeSelect) {
			approverTypeSelect.value = 'role';
		}

		clone.querySelectorAll('.required-toggle').forEach(function (toggle) {
			toggle.checked = true;
		});
		clone.querySelectorAll('.required-hidden-input').forEach(function (hiddenInput) {
			hiddenInput.value = '1';
		});

		const title = clone.querySelector('.workflow-step-title');
		if (title) {
			title.textContent = 'Approval Step';
		}

		container.appendChild(clone);
		syncApproverInputs(clone);
		syncRequiredHiddenValues();
		refreshRemoveButtons();

		clone.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		const stepNameInput = clone.querySelector('.step-name-input');
		if (stepNameInput instanceof HTMLInputElement) {
			stepNameInput.focus();
		}
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
			if (target.classList.contains('approver-type-select') || target.classList.contains('approver-role-select')) {
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