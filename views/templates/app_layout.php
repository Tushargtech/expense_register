<?php

if (!function_exists('buildAssetUrl')) {
	function buildAssetUrl(string $assetPath): string
	{
		$scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
		$scriptDir = str_replace('\\', '/', dirname($scriptName));
		$scriptDir = trim($scriptDir, '/');

		$pathSegments = [];
		if ($scriptDir !== '' && $scriptDir !== '.') {
			foreach (explode('/', $scriptDir) as $segment) {
				if ($segment === '') {
					continue;
				}
				$pathSegments[] = rawurlencode($segment);
			}
		}

		foreach (explode('/', ltrim($assetPath, '/')) as $segment) {
			if ($segment === '') {
				continue;
			}
			$pathSegments[] = rawurlencode($segment);
		}

		return '/' . implode('/', $pathSegments);
	}
}

if (!function_exists('renderAppLayoutStart')) {
	function renderAppLayoutStart(array $options = []): void
	{
		$title = isset($options['pageTitle']) ? (string) $options['pageTitle'] : 'Expense Register';
		$bodyClassName = isset($options['bodyClass']) ? (string) $options['bodyClass'] : '';
		$activeMenu = isset($options['activeMenu']) ? (string) $options['activeMenu'] : 'dashboard';
		$includeChrome = !isset($options['includeChrome']) || (bool) $options['includeChrome'] === true;
		$showNavbarControls = !isset($options['showNavbarControls']) || (bool) $options['showNavbarControls'] === true;
		$displayName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$normalizedDisplayName = strtolower(trim($displayName));
		if ($normalizedDisplayName === 'system administrator' || $normalizedDisplayName === 'system admininstrator') {
			$displayName = 'Admin';
		}

		?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo htmlspecialchars(buildAssetUrl('assets/css/app.css'), ENT_QUOTES, 'UTF-8'); ?>">
	<?php
	$extraStyles = isset($options['pageStyles']) && is_array($options['pageStyles']) ? $options['pageStyles'] : [];
	foreach ($extraStyles as $stylePath):
		$stylePath = (string) $stylePath;
		if ($stylePath === '' || basename($stylePath) === 'app.css') {
			continue;
		}
	?>
	<link rel="stylesheet" href="<?php echo htmlspecialchars(buildAssetUrl($stylePath), ENT_QUOTES, 'UTF-8'); ?>">
	<?php endforeach; ?>
</head>
<body class="<?php echo htmlspecialchars($bodyClassName, ENT_QUOTES, 'UTF-8'); ?>">
	<?php if ($includeChrome): ?>
		<!-- App Header -->
		<nav class="navbar navbar-dark fixed-top app-navbar">
			<div class="container-fluid px-3 px-md-4">
				<a class="navbar-brand fw-bold app-brand" href="<?php echo htmlspecialchars(buildCleanRouteUrl('dashboard'), ENT_QUOTES, 'UTF-8'); ?>">Expense Register</a>
				<?php if ($showNavbarControls): ?>
				<div class="d-flex align-items-center gap-3 ms-auto">
					<span id="navbarDateTime" class="navbar-text text-white-50 fw-semibold d-none d-md-inline">--</span>
					<span class="navbar-text text-white fw-semibold d-none d-md-inline"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
					<div class="dropdown profile-container">
						<button class="profile-icon" id="layoutProfileToggle" type="button" aria-label="Open profile menu" data-bs-toggle="dropdown" aria-expanded="false">👤</button>
						<ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 profile-dropdown-menu" aria-labelledby="layoutProfileToggle">
							<li><a class="dropdown-item" href="<?php echo htmlspecialchars(buildCleanRouteUrl('my-profile'), ENT_QUOTES, 'UTF-8'); ?>">My Profile</a></li>
							<li><a class="dropdown-item text-danger" href="<?php echo htmlspecialchars(buildCleanRouteUrl('logout'), ENT_QUOTES, 'UTF-8'); ?>">Logout</a></li>
						</ul>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</nav>

		<!-- App Sidebar -->
		<?php
		$rbac = new RbacService();
		$showSidebar = isset($options['showSidebar']) ? (bool) $options['showSidebar'] : true;
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
						'href' => buildCleanRouteUrl('dashboard'),
						'key' => 'dashboard',
						'icon' => 'bi-display',
					],
					$canViewUsersDepartments ? [
						'label' => 'Users',
						'href' => buildCleanRouteUrl('users'),
						'key' => 'user-list',
						'icon' => 'bi-people',
					] : null,
					$canViewUsersDepartments ? [
						'label' => 'Departments',
						'href' => buildCleanRouteUrl('departments'),
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
						'href' => buildCleanRouteUrl('expenses'),
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
						'href' => buildCleanRouteUrl('budget-categories'),
						'key' => 'budget-category-list',
						'icon' => 'bi-wallet2',
					] : null,
					$canManageFinancialSetup ? [
						'label' => 'Budget Upload',
						'href' => buildCleanRouteUrl('budget-uploader'),
						'key' => 'budget-uploader',
						'icon' => 'bi-upload',
					] : null,
					$canAccessBudgetMonitor ? [
						'label' => 'Budget Monitor',
						'href' => buildCleanRouteUrl('budget-monitor'),
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
						'href' => buildCleanRouteUrl('workflows'),
						'key' => 'workflow-list',
						'icon' => 'bi-diagram-3',
					] : null,
				],
			],
		];
		?>
		<?php if ($showSidebar): ?>
			<aside class="sidebar d-flex flex-column">
				<button type="button" id="sidebarToggle" class="sidebar-toggle-btn" aria-label="Toggle sidebar" title="Toggle sidebar">
					<i class="bi bi-list"></i>
				</button>
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
								<span class="sidebar-link-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
							</a>
						<?php endforeach; ?>
					</nav>
				</div>
			<?php endforeach; ?>
		</aside>
		<?php endif; ?>
	<?php endif; ?>
	<?php
	}
}

if (!function_exists('renderAppLayoutEnd')) {
	function renderAppLayoutEnd(): void
	{
		?>
	<footer class="footer app-footer">
		<div class="container-fluid text-center">
			© 2026 Footprints Childcare Pvt. Ltd.
		</div>
	</footer>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	<script>
	(function () {
	    var dateTimeEl = document.getElementById('navbarDateTime');
	    var toggleBtn = document.getElementById('sidebarToggle');
	    var collapsedKey = 'sidebar-collapsed';

	    function renderDateTime() {
	        if (!dateTimeEl) {
	            return;
	        }
	        var now = new Date();
	        var datePart = now.toLocaleDateString(undefined, {
	            year: 'numeric',
	            month: 'short',
	            day: '2-digit'
	        });
	        var timePart = now.toLocaleTimeString(undefined, {
	            hour: '2-digit',
	            minute: '2-digit'
	        });
	        dateTimeEl.textContent = datePart + ' ' + timePart;
	    }

	    function setCollapsedState(collapsed) {
	        document.body.classList.toggle('sidebar-collapsed', collapsed);
	        if (toggleBtn) {
	            toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
	        }
	        try {
	            localStorage.setItem(collapsedKey, collapsed ? '1' : '0');
	        } catch (error) {
	            // Ignore storage errors in private browsing modes.
	        }
	    }

	    renderDateTime();
	    window.setInterval(renderDateTime, 30000);

	    var initiallyCollapsed = false;
	    try {
	        initiallyCollapsed = localStorage.getItem(collapsedKey) === '1';
	    } catch (error) {
	        initiallyCollapsed = false;
	    }
	    if (initiallyCollapsed) {
	        setCollapsedState(true);
	    }

	    if (toggleBtn) {
	        toggleBtn.addEventListener('click', function () {
	            setCollapsedState(!document.body.classList.contains('sidebar-collapsed'));
	        });
	    }
	})();
	</script>
	</body>
	</html>
	<?php
	}
}
