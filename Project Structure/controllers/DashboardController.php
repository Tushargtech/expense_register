<?php

class DashboardController extends BaseController
{
	public function index(): void
	{
		$this->requireAuth();
		$this->render('module-1/view1.php', [
			'userName' => (string) ($_SESSION['auth']['name'] ?? 'User'),
		]);
	}
}
