<?php

$displayName = (string) ($_SESSION['auth']['name'] ?? 'User');
$normalizedDisplayName = strtolower(trim($displayName));
if ($normalizedDisplayName === 'system administrator' || $normalizedDisplayName === 'system admininstrator') {
	$displayName = 'Admin';
}
?>
<nav class="navbar navbar-dark fixed-top app-navbar">
	<div class="container-fluid px-3 px-md-4">
		<a class="navbar-brand fw-bold app-brand" href="?route=home">Expense Register</a>
		<div class="d-flex align-items-center gap-3 ms-auto">
			<span class="navbar-text text-white fw-semibold d-none d-md-inline"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
			<div class="dropdown profile-container">
				<button class="profile-icon" id="layoutProfileToggle" type="button" aria-label="Open profile menu" data-bs-toggle="dropdown" aria-expanded="false">👤</button>
				<ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 profile-dropdown-menu" aria-labelledby="layoutProfileToggle">
					<li><a class="dropdown-item" href="#">My Profile</a></li>
					<li><a class="dropdown-item text-danger" href="?route=logout">Logout</a></li>
				</ul>
			</div>
		</div>
	</div>
</nav>