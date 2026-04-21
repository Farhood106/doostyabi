<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $to): never
    {
        header('Location: ' . $to);
        exit;
    }

    public static function abort(int $statusCode = 404, string $message = 'Not found'): never
    {
        http_response_code($statusCode);
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        exit;
    }
}
