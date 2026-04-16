<?php
$departments = isset($departments) && is_array($departments) ? $departments : [];
$managers = isset($managers) && is_array($managers) ? $managers : [];
$roleOptions = isset($roleOptions) && is_array($roleOptions) ? $roleOptions : [];
$formError = isset($formError) ? (string) $formError : '';
$isEdit = isset($isEdit) ? (bool) $isEdit : false;
$formAction = isset($formAction) ? (string) $formAction : buildCleanRouteUrl('users/create');
$formTitle = isset($formTitle) ? (string) $formTitle : 'Add Employee';
$submitLabel = isset($submitLabel) ? (string) $submitLabel : 'Save Employee';
$user = isset($user) && is_array($user) ? $user : [];

$selectedName = (string) ($user['user_name'] ?? '');
$selectedEmail = (string) ($user['user_email'] ?? '');
$selectedRole = (string) ($user['user_role'] ?? 'employee');
$selectedDepartmentId = (int) ($user['department_id'] ?? 0);
$selectedManagerId = (int) ($user['manager_id'] ?? 0);
$selectedStatus = (int) ($user['user_is_active'] ?? 1);
?>

<main class="main">
	<div class="page-shell user-create-page">
		<div class="user-create-shell">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<section class="user-create-hero">
				<p class="user-create-kicker">Employee Onboarding</p>
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
							<h2 class="user-create-section-title">Account Information</h2>
							<p class="user-create-note">Fill all required fields to continue.</p>
						</div>
					</div>

					<div class="user-create-grid">
						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="name">User Name</label>
							<input type="text" class="user-create-input" id="name" name="name" placeholder="Enter full name" value="<?php echo htmlspecialchars($selectedName, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="role">Role</label>
							<select class="user-create-select" id="role" name="role" required>
								<?php foreach ($roleOptions as $roleOption): ?>
									<?php $roleValue = strtolower(trim((string) ($roleOption['value'] ?? ''))); ?>
									<?php $roleLabel = (string) ($roleOption['label'] ?? ucfirst($roleValue)); ?>
									<?php if ($roleValue === '') { continue; } ?>
									<option value="<?php echo htmlspecialchars($roleValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedRole === $roleValue ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="user-create-field user-create-field-medium">
							<label class="user-create-label" for="email">User Email</label>
							<input type="email" class="user-create-input" id="email" name="email" placeholder="name@example.com" value="<?php echo htmlspecialchars($selectedEmail, ENT_QUOTES, 'UTF-8'); ?>" required>
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
							<label class="user-create-label" for="manager_id">Manager</label>
							<select class="user-create-select" id="manager_id" name="manager_id" required>
								<option value="">Select Manager</option>
								<?php foreach ($managers as $manager): ?>
									<?php $managerId = (int) ($manager['user_id'] ?? 0); ?>
									<option value="<?php echo $managerId; ?>" <?php echo $selectedManagerId === $managerId ? 'selected' : ''; ?>>
										<?php echo htmlspecialchars((string) ($manager['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="user-create-field">
							<label class="user-create-label" for="user_is_active">Status</label>
							<select class="user-create-select" id="user_is_active" name="user_is_active" required>
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
						<a href="<?php echo htmlspecialchars(buildCleanRouteUrl('users'), ENT_QUOTES, 'UTF-8'); ?>" class="user-create-btn user-create-btn-secondary">Back to Employee List</a>
						<button type="submit" class="user-create-btn user-create-btn-primary"><?php echo htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8'); ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
</main>
