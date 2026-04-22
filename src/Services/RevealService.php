<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RevealRepository;
use InvalidArgumentException;

final class RevealService
{
    private const TYPE_TO_STAGE_FIELD = [
        'first_name' => 'first_name_reveal_stage',
        'photo' => 'photo_reveal_stage',
        'contact_info' => 'contact_reveal_stage',
        'deep_profile' => 'deep_profile_reveal_stage',
    ];

    private const TYPE_TO_VALUE_FIELD = [
        'first_name' => 'first_name',
        'photo' => 'photo_media_key',
        'contact_info' => 'contact_payload_json',
        'deep_profile' => 'deep_profile_payload_json',
    ];

    public function __construct(private readonly RevealRepository $repo) {}

    public function panel(int $matchId, int $userId): array
    {
        $match = $this->repo->matchForUser($matchId, $userId);
        if (!$match) {
            throw new InvalidArgumentException('forbidden_match');
        }

        $this->repo->expirePendingForMatch($matchId);

        $unlocked = [];
        $requests = $this->repo->unlockedRequestsForViewer($matchId, $userId);
        foreach ($requests as $r) {
            $ownerId = (int)$r['requested_by_user_id'];
            $type = (string)$r['reveal_type'];
            $data = $this->repo->revealableData($ownerId);
            if (!$data) {
                continue;
            }
            $valueField = self::TYPE_TO_VALUE_FIELD[$type] ?? null;
            if (!$valueField || empty($data[$valueField])) {
                continue;
            }
            $unlocked[] = [
                'reveal_type' => $type,
                'value' => $data[$valueField],
                'owner_user_id' => $ownerId,
            ];
        }

        return [
            'pending_incoming' => $this->repo->pendingRequestsForResponder($matchId, $userId),
            'pending_outgoing' => $this->repo->myPendingRequests($matchId, $userId),
            'unlocked' => $unlocked,
        ];
    }

    public function createRequest(int $matchId, int $requesterUserId, string $revealType): int
    {
        $match = $this->repo->matchForUser($matchId, $requesterUserId);
        if (!$match) {
            throw new InvalidArgumentException('forbidden_match');
        }

        if (!isset(self::TYPE_TO_STAGE_FIELD[$revealType])) {
            throw new InvalidArgumentException('invalid_reveal_type');
        }

        $data = $this->repo->revealableData($requesterUserId);
        if (!$data) {
            throw new InvalidArgumentException('no_reveal_data');
        }

        $stageField = self::TYPE_TO_STAGE_FIELD[$revealType];
        $stageRequired = (string)$data[$stageField];
        if (!$this->isStageAllowed((string)$match['status'], $stageRequired)) {
            throw new InvalidArgumentException('stage_not_allowed');
        }

        $valueField = self::TYPE_TO_VALUE_FIELD[$revealType];
        if (empty($data[$valueField])) {
            throw new InvalidArgumentException('reveal_value_missing');
        }

        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        $requestId = $this->repo->createRequest($matchId, $requesterUserId, $revealType, $stageRequired, $expiresAt);
        $this->repo->upsertConsent($requestId, $requesterUserId, 'accepted');

        return $requestId;
    }

    public function respond(int $requestId, int $actorUserId, string $decision): void
    {
        $req = $this->repo->requestById($requestId);
        if (!$req || ($req['status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('request_not_pending');
        }

        $match = $this->repo->matchForUser((int)$req['match_id'], $actorUserId);
        if (!$match) {
            throw new InvalidArgumentException('forbidden_match');
        }

        if ((int)$req['requested_by_user_id'] === $actorUserId) {
            throw new InvalidArgumentException('requester_cannot_respond');
        }

        if ($decision === 'accept') {
            $this->repo->upsertConsent($requestId, $actorUserId, 'accepted');
            $this->repo->updateRequestStatus($requestId, 'accepted');
            return;
        }

        if ($decision === 'decline') {
            $this->repo->upsertConsent($requestId, $actorUserId, 'declined');
            $this->repo->updateRequestStatus($requestId, 'declined');
            return;
        }

        throw new InvalidArgumentException('invalid_decision');
    }

    public function cancel(int $requestId, int $actorUserId): void
    {
        $req = $this->repo->requestById($requestId);
        if (!$req || ($req['status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('request_not_pending');
        }

        if ((int)$req['requested_by_user_id'] !== $actorUserId) {
            throw new InvalidArgumentException('forbidden_cancel');
        }

        $this->repo->updateRequestStatus($requestId, 'cancelled');
    }

    private function isStageAllowed(string $matchStatus, string $requiredStage): bool
    {
        $stageRank = ['stage_1' => 1, 'stage_2' => 2, 'stage_3' => 3];
        $currentStage = match ($matchStatus) {
            'chat_open' => 'stage_3',
            'mutual' => 'stage_2',
            default => 'stage_1',
        };

        return ($stageRank[$currentStage] ?? 0) >= ($stageRank[$requiredStage] ?? 99);
    }
}
