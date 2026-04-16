<?php

abstract class ApiBaseController
{
    protected ApiRequest $request;

    public function __construct()
    {
        $this->request = new ApiRequest();
    }

    protected function rbac(): RbacService
    {
        return new RbacService();
    }

    protected function authenticatedUser(): array
    {
        return isset($_SESSION['auth']) && is_array($_SESSION['auth']) ? $_SESSION['auth'] : [];
    }

    protected function ensureAuthenticated(): void
    {
        if (empty($this->authenticatedUser()['is_logged_in'])) {
            ApiResponse::error('Unauthorized', 401);
        }
    }

    protected function ensurePermission(bool $allowed, string $message = 'Forbidden'): void
    {
        if (!$allowed) {
            ApiResponse::error($message, 403);
        }
    }

    protected function jsonSuccess(mixed $data = null, array $meta = [], int $statusCode = 200): void
    {
        ApiResponse::success($data, $meta, $statusCode);
    }

    protected function jsonError(string $message, int $statusCode = 400, array $errors = []): void
    {
        ApiResponse::error($message, $statusCode, $errors);
    }

    protected function input(): array
    {
        return $this->request->input();
    }

    protected function method(): string
    {
        return $this->request->method();
    }

    protected function idFromQuery(string $key = 'id'): int
    {
        return $this->request->routeInt($key, 0);
    }

    protected function pagination(array $defaults = []): array
    {
        $page = max(1, (int) ($_GET['page'] ?? ($defaults['page'] ?? 1)));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? ($defaults['limit'] ?? 10))));

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ];
    }

    protected function emitBinary(string $payload, string $fileName, string $mimeType, bool $inline): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(200);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) strlen($payload));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . addslashes($fileName) . '"');
        header('X-Content-Type-Options: nosniff');
        echo $payload;
        exit;
    }
}