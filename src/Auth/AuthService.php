<?php

declare(strict_types=1);

namespace App\Auth;

use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(private readonly UserRepository $users) {}

    public function register(string $email, string $password, string $locale = 'fa'): int
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        return $this->users->create($email, $hash, $locale);
    }

    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);
        if (!$user) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }

    public function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['auth_user_id'] = (int)$user['id'];
        $_SESSION['locale'] = $user['preferred_locale'] ?? 'fa';
        $_SESSION['direction'] = $user['ui_direction'] === 'ltr' ? 'ltr' : 'rtl';
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function userId(): ?int
    {
        return isset($_SESSION['auth_user_id']) ? (int)$_SESSION['auth_user_id'] : null;
    }
}
