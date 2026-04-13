<?php

$activeMenu = isset($activeMenu) ? (string) $activeMenu : 'dashboard';
$rbac = new RbacService();
$canViewUsersDepartments = $rbac->canViewUsers() || $rbac->canViewDepartments();
$canAccessExpenses = $rbac->canAccessFinancialRequests();
$canViewBudgetCategories = $rbac->canViewBudgetCategories();
$canManageFinancialSetup = $rbac->canManageFinancialSetup();
$canAccessBudgetMonitor = $rbac->canAccessBudgetMonitor();
$canViewWorkflows = $rbac->canViewWorkflowList();

$sidebarGroups = [
	[
		'label' => 'Main',
		'items' => [
			[
				'label' => 'Dashboard',
				'href' => '?route=home',
				'key' => 'dashboard',
				'icon' => 'bi-display',
			],
			$canViewUsersDepartments ? [
				'label' => 'Users',
				'href' => '?route=users',
				'key' => 'user-list',
				'icon' => 'bi-people',
			] : null,
			$canViewUsersDepartments ? [
				'label' => 'Departments',
				'href' => '?route=departments',
				'key' => 'department-list',
				'icon' => 'bi-building',
			] : null,
		],
	],
	[
		'label' => 'Expense Management',
		'items' => [
			$canAccessExpenses ? [
				'label' => 'Expenses',
				'href' => '?route=expenses',
				'key' => 'expense-list',
				'icon' => 'bi-cash-stack',
			] : null,
		],
	],
	[
		'label' => 'Budget Management',
		'items' => [
			$canViewBudgetCategories ? [
				'label' => 'Budget Categories',
				'href' => '?route=budget-categories',
				'key' => 'budget-category-list',
				'icon' => 'bi-wallet2',
			] : null,
			$canManageFinancialSetup ? [
				'label' => 'Budget Upload',
				'href' => '?route=budget-uploader',
				'key' => 'budget-uploader',
				'icon' => 'bi-upload',
			] : null,
			$canAccessBudgetMonitor ? [
				'label' => 'Budget Monitor',
				'href' => '?route=budget-monitor',
				'key' => 'budget-monitor',
				'icon' => 'bi-graph-up',
			] : null,
		],
	],
	[
		'label' => 'Workflow Management',
		'items' => [
			$canViewWorkflows ? [
				'label' => 'Workflows',
				'href' => '?route=workflows',
				'key' => 'workflow-list',
				'icon' => 'bi-diagram-3',
			] : null,
		],
	],
];		

?>
<aside class="sidebar d-flex flex-column">
	<?php foreach ($sidebarGroups as $group): ?>
		<?php
		$items = array_values(array_filter($group['items'], static fn($item) => is_array($item)));
		if (count($items) === 0) {
			continue;
		}
		?>
		<div class="sidebar-section">
			<div class="sidebar-section-title"><?php echo htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8'); ?></div>
			<nav class="nav flex-column gap-2" aria-label="<?php echo htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8'); ?> Navigation">
				<?php foreach ($items as $item): ?>
					<a
						href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
						class="nav-link sidebar-link d-flex align-items-center gap-2 <?php echo $activeMenu === $item['key'] ? 'active' : ''; ?>"
					>
						<i class="bi <?php echo htmlspecialchars((string) ($item['icon'] ?? 'bi-dot'), ENT_QUOTES, 'UTF-8'); ?> sidebar-link-icon" aria-hidden="true"></i>
						<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		</div>
	<?php endforeach; ?>
</aside>
