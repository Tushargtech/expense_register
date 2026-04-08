<?php

$activeMenu = isset($activeMenu) ? (string) $activeMenu : 'dashboard';

$sidebarGroups = [
	[
		'label' => 'Main',
		'items' => [
			[
				'label' => 'Dashboard',
				'href' => '?route=module-1',
				'key' => 'dashboard',
				'icon' => 'bi-display',
			],
			[
				'label' => 'Users',
				'href' => '?route=users',
				'key' => 'user-list',
				'icon' => 'bi-people',
			],
			[
				'label' => 'Departments',
				'href' => '?route=departments',
				'key' => 'department-list',
				'icon' => 'bi-building',
			],
			[
				'label' => 'Budget Categories',
				'href' => '?route=budget-categories',
				'key' => 'budget-category-list',
				'icon' => 'bi-wallet2',
			],
		],
	],
];
?>
<aside class="sidebar d-flex flex-column">
	<?php foreach ($sidebarGroups as $group): ?>
		<div class="sidebar-section">
			<div class="sidebar-section-title"><?php echo htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8'); ?></div>
			<nav class="nav flex-column gap-2" aria-label="<?php echo htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8'); ?> Navigation">
				<?php foreach ($group['items'] as $item): ?>
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
