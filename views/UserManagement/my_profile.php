<?php
$userProfile = isset($userProfile) && is_array($userProfile) ? $userProfile : [];
$isActive = (int) ($userProfile['user_is_active'] ?? 0) === 1;
$statusLabel = $isActive ? 'Active' : 'Inactive';
?>

<main class="main">
	<div class="page-shell user-create-page my-profile-page">
		<div class="user-create-shell">
			<?php require ROOT_PATH . '/views/templates/flash_message.php'; ?>

			<section class="user-create-hero">
				<p class="user-create-kicker">User Management</p>
				<h1 class="user-create-title">My Profile</h1>
			</section>

			<section class="user-create-section">
				<div class="user-create-head">
					<div>
						<h2 class="user-create-section-title">Employee Details</h2>
						<p class="user-create-note">Read-only details from your account record.</p>
					</div>
				</div>

				<div class="user-create-grid">
					<div class="user-create-field user-create-field-medium">
						<label class="user-create-label">User Name</label>
						<input type="text" class="user-create-input" value="<?php echo htmlspecialchars((string) ($userProfile['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly>
					</div>

					<div class="user-create-field user-create-field-medium">
						<label class="user-create-label">Email</label>
						<input type="text" class="user-create-input" value="<?php echo htmlspecialchars((string) ($userProfile['user_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly>
					</div>

					<div class="user-create-field">
						<label class="user-create-label">Role</label>
						<input type="text" class="user-create-input" value="<?php echo htmlspecialchars((string) ($userProfile['user_role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly>
					</div>

					<div class="user-create-field">
						<label class="user-create-label">Department Name</label>
						<input type="text" class="user-create-input" value="<?php echo htmlspecialchars((string) ($userProfile['dept_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>" readonly>
					</div>

					<div class="user-create-field">
						<label class="user-create-label">Manager Name</label>
						<input type="text" class="user-create-input" value="<?php echo htmlspecialchars((string) ($userProfile['manager_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>" readonly>
					</div>

					<div class="user-create-field">
						<label class="user-create-label">Status</label>
						<input type="text" class="user-create-input" value="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>" readonly>
					</div>
				</div>
			</section>
		</div>
	</div>
</main>