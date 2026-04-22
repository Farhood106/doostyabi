<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ChatRepository;
use InvalidArgumentException;

final class ChatService
{
    public function __construct(private readonly ChatRepository $repo) {}

    public function openContext(int $userId, ?int $chatId, ?int $matchId): array
    {
        $chat = null;
        if ($chatId !== null && $chatId > 0) {
            $chat = $this->repo->accessibleChatById($chatId, $userId);
        } elseif ($matchId !== null && $matchId > 0) {
            $chat = $this->repo->accessibleChatByMatch($matchId, $userId);
        }

        if (!$chat) {
            throw new InvalidArgumentException('chat_not_found_or_forbidden');
        }

        if (($chat['chat_status'] ?? '') !== 'open') {
            throw new InvalidArgumentException('chat_closed');
        }

        if (!in_array(($chat['match_status'] ?? ''), ['chat_open', 'mutual'], true)) {
            throw new InvalidArgumentException('match_not_chat_open');
        }

        $chat['messages'] = $this->repo->recentMessages((int)$chat['chat_id'], 80);

        return $chat;
    }

    public function sendMessage(int $userId, int $chatId, string $body, string $type = 'text'): int
    {
        $context = $this->openContext($userId, $chatId, null);

        $type = trim($type);
        if (!in_array($type, ['text', 'prompt', 'system'], true)) {
            throw new InvalidArgumentException('invalid_message_type');
        }

        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) {
            throw new InvalidArgumentException('invalid_message_body');
        }

        return $this->repo->insertMessage((int)$context['chat_id'], $userId, $body, $type);
    }

    public function poll(int $userId, int $chatId, int $sinceId): array
    {
        $context = $this->openContext($userId, $chatId, null);
        return $this->repo->messagesAfterId((int)$context['chat_id'], max(0, $sinceId), 50);
    }
}
