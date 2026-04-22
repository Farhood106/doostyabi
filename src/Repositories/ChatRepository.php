<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ChatRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function accessibleChatById(int $chatId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.id AS chat_id, c.match_id, c.status AS chat_status,
                    m.status AS match_status, m.user_a_id, m.user_b_id
             FROM chats c
             JOIN matches m ON m.id = c.match_id
             WHERE c.id = :chat_id
               AND (:uid = m.user_a_id OR :uid = m.user_b_id)
             LIMIT 1"
        );
        $stmt->execute(['chat_id' => $chatId, 'uid' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function accessibleChatByMatch(int $matchId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.id AS chat_id, c.match_id, c.status AS chat_status,
                    m.status AS match_status, m.user_a_id, m.user_b_id
             FROM chats c
             JOIN matches m ON m.id = c.match_id
             WHERE c.match_id = :match_id
               AND (:uid = m.user_a_id OR :uid = m.user_b_id)
             LIMIT 1"
        );
        $stmt->execute(['match_id' => $matchId, 'uid' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function recentMessages(int $chatId, int $limit = 80): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, chat_id, sender_user_id, message_body, message_type, created_at
             FROM messages
             WHERE chat_id = :chat_id
             ORDER BY id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':chat_id', $chatId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_reverse($stmt->fetchAll());
    }

    public function messagesAfterId(int $chatId, int $sinceId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, chat_id, sender_user_id, message_body, message_type, created_at
             FROM messages
             WHERE chat_id = :chat_id
               AND id > :since_id
             ORDER BY id ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':chat_id', $chatId, PDO::PARAM_INT);
        $stmt->bindValue(':since_id', $sinceId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function insertMessage(int $chatId, int $senderUserId, string $body, string $type): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO messages
                (chat_id, sender_user_id, message_body, message_type, metadata_json, moderation_state, created_at)
             VALUES
                (:chat_id, :sender, :body, :type, NULL, 'clean', :created)"
        );
        $stmt->execute([
            'chat_id' => $chatId,
            'sender' => $senderUserId,
            'body' => $body,
            'type' => $type,
            'created' => date('Y-m-d H:i:s'),
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}
