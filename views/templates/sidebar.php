<?php

$activeMenu = isset($activeMenu) ? (string) $activeMenu : 'dashboard';

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
		],
	],
	[
		'label' => 'Budget Management',
		'items' => [
			[
				'label' => 'Budget Categories',
				'href' => '?route=budget-categories',
				'key' => 'budget-category-list',
				'icon' => 'bi-wallet2',
			],
			[
				'label' => 'Budget Upload',
				'href' => '?route=budget-uploader',
				'key' => 'budget-uploader',
				'icon' => 'bi-upload',
			],
			[
				'label' => 'Budget Monitor',
				'href' => '?route=budget-monitor',
				'key' => 'budget-monitor',
				'icon' => 'bi-graph-up',
			]
		],
	],

	[
		'label' => 'Expense Management',
		'items' => [
			[
				'label' => 'Expense Requests',
				'href' => '?route=expenses',
				'key' => 'expense-list',
				'icon' => 'bi-currency-dollar',
			],
		],
	],
];

$currentRole = strtolower(trim((string) ($_SESSION['auth']['role'] ?? '')));
$canReviewExpenses = in_array($currentRole, ['manager', 'finance'], true);

if ($canReviewExpenses) {
	$sidebarGroups[2]['items'][] = [
		'label' => 'Expense Review & Actions',
		'href' => '?route=expenses/review-actions',
		'key' => 'expense-review-actions',
		'icon' => 'bi-clipboard-check',
	];
}

$sidebarGroups[] = [
	'label' => 'Workflow Management',
	'items' => [
		[
			'label' => 'Workflows',
			'href' => '?route=workflows',
			'key' => 'workflow-list',
			'icon' => 'bi-list',
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
