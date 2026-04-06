<?php

$activeMenu = isset($activeMenu) ? (string) $activeMenu : 'dashboard';
?>
<aside class="sidebar d-flex flex-column">
	<div class="sidebar-section">
		<div class="sidebar-section-title">Main</div>
		<nav class="nav flex-column gap-2" aria-label="Main Navigation">
			<a href="?route=module-1" class="nav-link sidebar-link <?php echo $activeMenu === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
		</nav>
	</div>
	<div class="sidebar-section mt-2">
		<div class="sidebar-section-title">Expenses</div>
		<nav class="nav flex-column gap-2" aria-label="Expense Navigation">
			<a href="#" class="nav-link sidebar-link <?php echo $activeMenu === 'create-expense' ? 'active' : ''; ?>">Create Expense</a>
			<a href="#" class="nav-link sidebar-link <?php echo $activeMenu === 'expense-history' ? 'active' : ''; ?>">Expense History</a>
		</nav>
	</div>
</aside>
