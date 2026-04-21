<?php

declare(strict_types=1);

namespace App\Security;

final class Csrf
{
    private const KEY = '_csrf_token';

    public function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    public function validate(?string $token): bool
    {
        $sessionToken = $_SESSION[self::KEY] ?? '';
        return is_string($token) && is_string($sessionToken) && hash_equals($sessionToken, $token);
    }
}
