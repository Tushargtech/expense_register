<?php

class ApiResponse
{
    public static function send(array $payload, int $statusCode = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, array $meta = [], int $statusCode = 200): void
    {
        $payload = ['success' => true];
        if ($data !== null) {
            $payload['data'] = $data;
        }
        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        self::send($payload, $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        self::send($payload, $statusCode);
    }
}