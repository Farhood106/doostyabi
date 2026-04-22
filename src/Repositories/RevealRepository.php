<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RevealRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function matchForUser(int $matchId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, user_a_id, user_b_id, status FROM matches WHERE id = :mid AND (:uid = user_a_id OR :uid = user_b_id) LIMIT 1');
        $stmt->execute(['mid' => $matchId, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function revealableData(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM revealable_profile_data WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createRequest(int $matchId, int $requestedBy, string $revealType, string $stageRequired, ?string $expiresAt): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reveal_requests (match_id, requested_by_user_id, reveal_type, stage_required, requested_at, expires_at, status)
             VALUES (:match_id, :requested_by, :reveal_type, :stage_required, :requested_at, :expires_at, :status)'
        );
        $stmt->execute([
            'match_id' => $matchId,
            'requested_by' => $requestedBy,
            'reveal_type' => $revealType,
            'stage_required' => $stageRequired,
            'requested_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt,
            'status' => 'pending',
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function upsertConsent(int $requestId, int $userId, string $consentStatus): void
    {
        $sql = 'INSERT INTO reveal_consents (reveal_request_id, user_id, consent_status, consented_at)
                VALUES (:rid, :uid, :status, :consented_at)';

        if ($this->isSqlite()) {
            $sql .= ' ON CONFLICT(reveal_request_id, user_id) DO UPDATE SET
                        consent_status = excluded.consent_status,
                        consented_at = excluded.consented_at';
        } else {
            $sql .= ' ON DUPLICATE KEY UPDATE
                        consent_status = VALUES(consent_status),
                        consented_at = VALUES(consented_at)';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'rid' => $requestId,
            'uid' => $userId,
            'status' => $consentStatus,
            'consented_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function requestById(int $requestId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reveal_requests WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $requestId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateRequestStatus(int $requestId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE reveal_requests SET status = :status, resolved_at = :resolved WHERE id = :id');
        $stmt->execute(['status' => $status, 'resolved' => date('Y-m-d H:i:s'), 'id' => $requestId]);
    }

    public function expirePendingForMatch(int $matchId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE reveal_requests
             SET status = 'expired', resolved_at = :resolved
             WHERE match_id = :match_id
               AND status = 'pending'
               AND expires_at IS NOT NULL
               AND expires_at < :now"
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute(['resolved' => $now, 'match_id' => $matchId, 'now' => $now]);
    }

    public function pendingRequestsForResponder(int $matchId, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM reveal_requests
             WHERE match_id = :match_id
               AND status = :status
               AND requested_by_user_id <> :uid
             ORDER BY requested_at DESC'
        );
        $stmt->execute(['match_id' => $matchId, 'status' => 'pending', 'uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function myPendingRequests(int $matchId, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM reveal_requests
             WHERE match_id = :match_id
               AND status = :status
               AND requested_by_user_id = :uid
             ORDER BY requested_at DESC'
        );
        $stmt->execute(['match_id' => $matchId, 'status' => 'pending', 'uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function unlockedRequestsForViewer(int $matchId, int $viewerUserId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT rr.*
             FROM reveal_requests rr
             JOIN reveal_consents c_req ON c_req.reveal_request_id = rr.id AND c_req.user_id = rr.requested_by_user_id AND c_req.consent_status = 'accepted'
             JOIN matches m ON m.id = rr.match_id
             JOIN reveal_consents c_other ON c_other.reveal_request_id = rr.id
             WHERE rr.match_id = :match_id
               AND rr.status = 'accepted'
               AND rr.requested_by_user_id <> :viewer
               AND ((m.user_a_id = :viewer AND c_other.user_id = m.user_a_id) OR (m.user_b_id = :viewer AND c_other.user_id = m.user_b_id))
               AND c_other.consent_status = 'accepted'
             ORDER BY rr.requested_at ASC"
        );
        $stmt->execute(['match_id' => $matchId, 'viewer' => $viewerUserId]);
        return $stmt->fetchAll();
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }
}
