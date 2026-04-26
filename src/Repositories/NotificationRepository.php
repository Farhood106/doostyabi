<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class NotificationRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function templateIdByKey(string $templateKey): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM notification_templates WHERE template_key = :template_key AND is_active = 1 LIMIT 1');
        $stmt->execute(['template_key' => $templateKey]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int)$id;
    }

    public function create(int $userId, int $templateId, ?int $actorUserId, ?string $entityType, ?int $entityId, array $payload = []): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (user_id, template_id, actor_user_id, entity_type, entity_id, payload_json, is_read, delivered_at, created_at)
             VALUES (:user_id, :template_id, :actor_user_id, :entity_type, :entity_id, :payload_json, 0, :delivered_at, :created_at)'
        );

        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            'user_id' => $userId,
            'template_id' => $templateId,
            'actor_user_id' => $actorUserId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'delivered_at' => $now,
            'created_at' => $now,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function recentForUser(int $userId, int $limit = 12): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.id, n.user_id, n.template_id, n.actor_user_id, n.entity_type, n.entity_id, n.payload_json, n.is_read, n.created_at,
                    t.template_key, t.title_text_key, t.body_text_key
             FROM notifications n
             JOIN notification_templates t ON t.id = n.template_id
             WHERE n.user_id = :uid
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['payload'] = json_decode((string)($row['payload_json'] ?? '{}'), true) ?? [];
            unset($row['payload_json']);
        }

        return $rows;
    }

    public function unreadCount(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id = :uid AND is_read = 0');
        $stmt->execute(['uid' => $userId]);

        return (int)($stmt->fetch()['c'] ?? 0);
    }

    public function markRead(int $userId, int $notificationId): void
    {
        $stmt = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $notificationId, 'uid' => $userId]);
    }

    public function markAllRead(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0');
        $stmt->execute(['uid' => $userId]);
    }
}
