<?php

if (!function_exists('flash_set')) {
	function flash_set(string $type, string $message): void
	{
		$_SESSION['flash_message'] = [
			'type' => $type,
			'message' => $message,
		];
	}
}

if (!function_exists('flash_success')) {
	function flash_success(string $message): void
	{
		flash_set('success', $message);
	}
}

if (!function_exists('flash_error')) {
	function flash_error(string $message): void
	{
		flash_set('error', $message);
	}
}

if (!function_exists('flash_consume')) {
	function flash_consume(): array
	{
		$flash = [];

		if (isset($_SESSION['flash_message']) && is_array($_SESSION['flash_message'])) {
			$flash = $_SESSION['flash_message'];
			unset($_SESSION['flash_message']);
		}

		return [
			'type' => (string) ($flash['type'] ?? ''),
			'message' => (string) ($flash['message'] ?? ''),
		];
	}
}