<?php

class BaseController
{
	protected function render(string $viewPath, array $data = []): void
	{
		extract($data, EXTR_SKIP);
		require ROOT_PATH . '/views/' . ltrim($viewPath, '/');
	}

	protected function redirect(string $route): void
	{
		header('Location: ?route=' . urlencode($route));
		exit;
	}

	protected function setFlash(string $key, string $message): void
	{
		$_SESSION[$key] = $message;
	}

	protected function pullFlash(string $key): string
	{
		$value = (string) ($_SESSION[$key] ?? '');
		unset($_SESSION[$key]);
		return $value;
	}

	protected function isAuthenticated(): bool
	{
		return !empty($_SESSION['auth']['is_logged_in']);
	}

	protected function requireAuth(): void
	{
		if (!$this->isAuthenticated()) {
			$this->setFlash('auth_error', 'Please login to continue.');
			$this->redirect('view2');
		}
	}
}
