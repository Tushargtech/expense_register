<?php

class WorkflowListController
{
	private function ensureAuthenticated(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			$_SESSION['auth_error'] = 'Please login to continue.';
			header('Location: ?route=dashboard');
			exit;
		}
	}

	public function list(): void
	{
		$this->ensureAuthenticated();

		$workflowModel = new WorkflowListModel();

		$filters = [
			'search' => trim((string) ($_GET['search'] ?? '')),
			'status' => trim((string) ($_GET['status'] ?? '')),
		];

		$perPage = 10;
		$currentPage = max(1, (int) ($_GET['page'] ?? 1));
		$totalWorkflows = $workflowModel->countAllWorkflows($filters);
		$totalPages = max(1, (int) ceil($totalWorkflows / $perPage));

		if ($currentPage > $totalPages) {
			$currentPage = $totalPages;
		}

		$offset = ($currentPage - 1) * $perPage;
		$workflows = $workflowModel->getAllWorkflows($filters, $perPage, $offset);

		$pageTitle = 'Workflow List - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/list.css'];
		$envConfig = $GLOBALS['envConfig'] ?? [];
		$userName = (string) ($_SESSION['auth']['name'] ?? 'User');
		$activeMenu = 'workflow-list';

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/workflow_list.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}
}
