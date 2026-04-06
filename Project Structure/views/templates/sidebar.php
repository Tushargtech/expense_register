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
			],
			[
				'label' => 'User List',
				'href' => '?route=users',
				'key' => 'user-list',
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
						class="nav-link sidebar-link <?php echo $activeMenu === $item['key'] ? 'active' : ''; ?>"
					>
						<?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		</div>
	<?php endforeach; ?>
</aside>
