<?php

class ApiRequest
{
    public function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function routeInt(string $key, int $default = 0): int
    {
        return (int) ($this->routeParam($key, $default) ?? $default);
    }

    public function queryString(string $key, string $default = ''): string
    {
        return trim((string) ($_GET[$key] ?? $default));
    }

    public function input(): array
    {
        $body = [];
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        if (!empty($_POST)) {
            $body = array_replace($body, $_POST);
        }

        return $body;
    }

    public function files(): array
    {
        return $_FILES;
    }
}