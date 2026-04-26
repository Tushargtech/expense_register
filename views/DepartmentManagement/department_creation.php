<?php
$managers = isset($managers) && is_array($managers) ? $managers : [];
$formError = isset($formError) ? (string) $formError : '';
$isEdit = isset($isEdit) ? (bool) $isEdit : false;
$formAction = isset($formAction) ? (string) $formAction : buildCleanRouteUrl('departments/create');
$formTitle = isset($formTitle) ? (string) $formTitle : 'Create Department';
$submitLabel = isset($submitLabel) ? (string) $submitLabel : 'Create Department';
$department = isset($department) && is_array($department) ? $department : [];

$selectedName = (string) ($department['department_name'] ?? '');
$selectedCode = (string) ($department['department_code'] ?? '');
$selectedHeadUserId = (int) ($department['department_head_user_id'] ?? 0);
?>

<main class="main">
	<div class="page-shell user-create-page">
		<div class="user-create-shell">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<section class="user-create-hero">
				<p class="user-create-kicker">Department Management</p>
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
							<h2 class="user-create-section-title">Department Information</h2>
							<p class="user-create-note">Fill all required fields to continue.</p>
						</div>
					</div>

					<div class="user-create-grid">
						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="department_name">Department Name <span class="text-danger">*</span></label>
							<input type="text" class="user-create-input" id="department_name" name="department_name" placeholder="Enter department name" value="<?php echo htmlspecialchars($selectedName, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="department_code">Department Code <span class="text-danger">*</span></label>
							<input type="text" class="user-create-input" id="department_code" name="department_code" placeholder="DEPT001" value="<?php echo htmlspecialchars($selectedCode, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>

						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="department_head_user_id">Department Head <span class="text-danger">*</span></label>
							<select class="user-create-select" id="department_head_user_id" name="department_head_user_id" required>
								<option value="">Select Department Head</option>
								<?php foreach ($managers as $manager): ?>
									<?php $managerId = (int) ($manager['user_id'] ?? 0); ?>
									<option value="<?php echo $managerId; ?>" <?php echo $selectedHeadUserId === $managerId ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars((string) ($manager['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</section>

				<div class="user-create-action-bar">
					<div class="user-create-action-copy">
						<strong><?php echo $isEdit ? 'Review changes before updating' : 'Review before saving'; ?></strong>
					</div>
					<div class="user-create-actions">
						<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('departments'), ENT_QUOTES, 'UTF-8'); ?>" class="user-create-btn user-create-btn-secondary">Back to Department List</a>
						<button type="submit" class="user-create-btn user-create-btn-primary"><?php echo htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8'); ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
</main>
