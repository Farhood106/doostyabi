<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GoalRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function activeGoals(): array
    {
        $stmt = $this->pdo->query('SELECT id, slug, title_key, description_key FROM goals WHERE is_active = 1 ORDER BY sort_order ASC');
        return $stmt->fetchAll();
    }
}
