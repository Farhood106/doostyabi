<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\UserFriendlyException;
use PDO;
use PDOException;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $email, string $passwordHash, string $locale = 'fa'): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (email, password_hash, preferred_locale, ui_direction, created_at, updated_at) VALUES (:email, :hash, :locale, :dir, NOW(), NOW())'
            );
            $stmt->execute([
                'email' => $email,
                'hash' => $passwordHash,
                'locale' => $locale,
                'dir' => $locale === 'fa' ? 'rtl' : 'ltr',
            ]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new UserFriendlyException('auth.email_taken');
            }

            throw new UserFriendlyException('common.unexpected_error');
        }

        return (int)$this->pdo->lastInsertId();
    }
}
