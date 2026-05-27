<?php

// helpers/Response.php

declare(strict_types=1);

class Response
{
    public static function json(
        mixed $data,
        int $statusCode = 200,
        bool $success = true,
        string $message = ''
    ): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $body = ['success' => $success];

        if ($message !== '') {
            $body['message'] = $message;
        }

        if ($data !== null) {
            $body['data'] = $data;
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = '', int $code = 200): void
    {
        self::json($data, $code, true, $message);
    }

    public static function error(string $message, int $code = 400, mixed $errors = null): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');

        $body = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function unauthorized(string $message = 'Unauthorized.'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden.'): void
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Resource not found.'): void
    {
        self::error($message, 404);
    }

    public static function serverError(string $message = 'Internal server error.'): void
    {
        self::error($message, 500);
    }
}
