<?php

$displayName = (string) ($_SESSION['auth']['name'] ?? 'User');
?>
<nav class="navbar">
	<div class="navbar-logo">Expense Register</div>
	<div class="d-flex align-items-center gap-3">
		<span><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
		<a href="?route=logout" class="btn btn-sm btn-outline-light">Logout</a>
	</div>
</nav>
