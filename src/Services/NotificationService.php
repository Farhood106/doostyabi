<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;

final class NotificationService
{
    public function __construct(private readonly NotificationRepository $repo) {}

    public function emit(string $type, int $userId, ?int $actorUserId = null, ?string $entityType = null, ?int $entityId = null, array $payload = []): void
    {
        $templateId = $this->repo->templateIdByKey('notification.' . $type);
        if ($templateId === null) {
            return;
        }

        $this->repo->create($userId, $templateId, $actorUserId, $entityType, $entityId, $payload);
    }

    public function recentForUser(int $userId, int $limit = 12): array
    {
        return $this->repo->recentForUser($userId, $limit);
    }

    public function unreadCount(int $userId): int
    {
        return $this->repo->unreadCount($userId);
    }

    public function markRead(int $userId, int $notificationId): void
    {
        $this->repo->markRead($userId, $notificationId);
    }

    public function markAllRead(int $userId): void
    {
        $this->repo->markAllRead($userId);
    }
}
