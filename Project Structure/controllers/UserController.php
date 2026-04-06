<?php

class UserController
{
	public function list(): void
	{
		// Require authenticated session before checking authorization role.
		if (empty($_SESSION['auth']['is_logged_in'])) {
			$_SESSION['auth_error'] = 'Please login to continue.';
			header('Location: ?route=view2');
			exit;
		}

		// Keep role-aware behavior but avoid blocking valid logged-in sessions
		// when role is not yet present in session (legacy sessions or stale cookies).
		$sessionRole = (string) ($_SESSION['auth']['role'] ?? $_SESSION['role'] ?? '');
		$normalizedRole = strtolower(trim($sessionRole));
		$allowedRoles = ['admin', 'hr', 'system administrator', 'employee', 'emp', 'manager', 'finance'];

		if ($normalizedRole !== '' && !in_array($normalizedRole, $allowedRoles, true)) {
			header('Location: ?route=module-1&error=unauthorized');
			exit;
		}

		$userModel = new UserModel();

		$filters = [
			'search' => trim((string) ($_GET['search'] ?? '')),
			'role' => trim((string) ($_GET['role'] ?? '')),
			'department' => trim((string) ($_GET['department'] ?? '')),
			'status' => trim((string) ($_GET['status'] ?? '')),
		];

		$perPage = 10;
		$currentPage = max(1, (int) ($_GET['page'] ?? 1));
		$totalUsers = $userModel->countAllUsers($filters);
		$totalPages = max(1, (int) ceil($totalUsers / $perPage));
		if ($currentPage > $totalPages) {
			$currentPage = $totalPages;
		}
		$offset = ($currentPage - 1) * $perPage;

		$users = $userModel->getAllUsers($filters, $perPage, $offset);
		$roleOptions = $userModel->getRoleOptions();
		$departmentOptions = $userModel->getDepartmentOptions();

		$pageTitle = 'User Management - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/user_list.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'user-list';

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/user_list.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}
}

