<?php

class WorkflowViewController
{
	private function ensureAuthenticated(): void
	{
		if (empty($_SESSION['auth']['is_logged_in'])) {
			flash_error('Please login to continue.');
			header('Location: ?route=dashboard');
			exit;
		}
	}

	public function index(): void
	{
		$this->ensureAuthenticated();

		$auth = $_SESSION['auth'] ?? [];
		$userId = (int) ($auth['user_id'] ?? 0);
		if ($userId <= 0) {
			flash_error('Unable to identify user session. Please login again.');
			header('Location: ?route=logout');
			exit;
		}

		$model = new WorkflowViewModel();
		$pendingRequests = $model->getPendingRequestsForUser($userId);

		$workflowIds = [];
		foreach ($pendingRequests as $request) {
			$workflowId = (int) ($request['workflow_id'] ?? 0);
			if ($workflowId > 0) {
				$workflowIds[$workflowId] = true;
			}
		}

		$workflowStepsMap = $model->getWorkflowStepsByWorkflowIds(array_keys($workflowIds));

		$selectedRequestId = (int) ($_GET['id'] ?? $_GET['request_id'] ?? 0);
		$selectedRequest = null;
		foreach ($pendingRequests as $request) {
			if ((int) ($request['request_id'] ?? 0) === $selectedRequestId) {
				$selectedRequest = $request;
				break;
			}
		}

		if ($selectedRequest === null && !empty($pendingRequests)) {
			$selectedRequest = $pendingRequests[0];
			$selectedRequestId = (int) ($selectedRequest['request_id'] ?? 0);
		}

		$selectedWorkflowSteps = [];
		if (is_array($selectedRequest)) {
			$selectedWorkflowId = (int) ($selectedRequest['workflow_id'] ?? 0);
			$selectedWorkflowSteps = $workflowStepsMap[$selectedWorkflowId] ?? [];
		}

		$pageTitle = 'Workflow View - Expense Register';
		$pageStyles = ['assets/css/dashboard.css', 'assets/css/list.css'];
		$activeMenu = 'workflow-view';

		require ROOT_PATH . '/views/templates/header.php';
		require ROOT_PATH . '/views/templates/navbar.php';
		require ROOT_PATH . '/views/templates/sidebar.php';
		require ROOT_PATH . '/views/module-1/workflow_view.php';
		require ROOT_PATH . '/views/templates/footer.php';
	}
}
