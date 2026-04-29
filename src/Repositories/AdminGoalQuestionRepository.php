<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AdminGoalQuestionRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function goals(): array
    {
        $stmt = $this->pdo->query('SELECT id, slug, title_key, is_active, sort_order FROM goals ORDER BY sort_order ASC, id ASC');
        return $stmt->fetchAll();
    }

    public function definitionsByGoalId(int $goalId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM goal_preference_definitions WHERE goal_id = :gid ORDER BY is_active DESC, id ASC');
        $stmt->execute(['gid' => $goalId]);
        return $stmt->fetchAll();
    }

    public function upsertDefinition(array $data): void
    {
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $stmt = $this->pdo->prepare('UPDATE goal_preference_definitions SET pref_key=:pref_key,label_key=:label_key,helper_text_key=:helper_text_key,input_type=:input_type,value_type=:value_type,allowed_values_json=:allowed_values_json,is_required=:is_required,weight=:weight,is_active=:is_active,updated_at=NOW() WHERE id=:id');
            $stmt->execute($data + ['id' => $id]);
            return;
        }
        $sql = 'INSERT INTO goal_preference_definitions (goal_id,pref_key,label_key,helper_text_key,input_type,value_type,allowed_values_json,weight,is_required,is_active,created_at,updated_at) VALUES (:goal_id,:pref_key,:label_key,:helper_text_key,:input_type,:value_type,:allowed_values_json,:weight,:is_required,:is_active,NOW(),NOW())';
        if ($this->isSqlite()) {
            $sql .= ' ON CONFLICT(goal_id,pref_key) DO UPDATE SET label_key=excluded.label_key,helper_text_key=excluded.helper_text_key,input_type=excluded.input_type,value_type=excluded.value_type,allowed_values_json=excluded.allowed_values_json,weight=excluded.weight,is_required=excluded.is_required,is_active=excluded.is_active,updated_at=excluded.updated_at';
        } else {
            $sql .= ' ON DUPLICATE KEY UPDATE label_key=VALUES(label_key),helper_text_key=VALUES(helper_text_key),input_type=VALUES(input_type),value_type=VALUES(value_type),allowed_values_json=VALUES(allowed_values_json),weight=VALUES(weight),is_required=VALUES(is_required),is_active=VALUES(is_active),updated_at=NOW()';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
    }

    public function toggleDefinition(int $id, int $isActive): void
    {
        $stmt = $this->pdo->prepare('UPDATE goal_preference_definitions SET is_active = :a, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['a' => $isActive, 'id' => $id]);
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }
}
