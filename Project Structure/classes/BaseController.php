<?php

/**
 * BaseController provides reusable MVC response helpers.
 *
 * Flow connection:
 * - Router calls controller methods.
 * - Controller returns HTML by rendering a view file from /views.
 * - View receives only the data it needs.
 */
abstract class BaseController
{
    protected function render(string $viewPath, array $data = []): void
    {
        $fullPath = ROOT_PATH . '/views/' . $viewPath . '.php';
        if (!is_file($fullPath)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($viewPath, ENT_QUOTES, 'UTF-8');
            exit;
        }

        extract($data, EXTR_SKIP);
        require $fullPath;
        exit;
    }

    protected function redirect(string $route): void
    {
        header('Location: ?route=' . urlencode($route));
        exit;
    }
}
