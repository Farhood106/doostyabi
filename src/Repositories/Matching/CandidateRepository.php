<?php

declare(strict_types=1);

namespace App\Repositories\Matching;

use PDO;

final class CandidateRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function usersBatch(int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id AS user_id
             FROM users u
             JOIN profiles p ON p.user_id = u.id
             WHERE u.status = 'active'
             ORDER BY u.id ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function userContext(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id AS user_id, u.status, p.*
             FROM users u
             JOIN profiles p ON p.user_id = u.id
             WHERE u.id = :uid
             LIMIT 1"
        );
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['goals'] = $this->activeGoalIds($userId);
        $row['boundaries'] = $this->boundaries($userId);
        $row['availability'] = $this->availability($userId);

        return $row;
    }

    public function strictNearbyL5(int $userId, string $countryCode, string $regionCode, string $l5, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id AS user_id
             FROM users u
             JOIN profiles p ON p.user_id = u.id
             WHERE u.status = 'active'
               AND u.id <> :uid
               AND p.country_code = :country
               AND p.region_code = :region
               AND p.location_cell_l5 = :l5
             ORDER BY u.id ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':country', $countryCode);
        $stmt->bindValue(':region', $regionCode);
        $stmt->bindValue(':l5', $l5);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function relaxedNearbyL4(int $userId, string $countryCode, string $regionCode, string $l4, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id AS user_id
             FROM users u
             JOIN profiles p ON p.user_id = u.id
             WHERE u.status = 'active'
               AND u.id <> :uid
               AND p.country_code = :country
               AND p.region_code = :region
               AND p.location_cell_l4 = :l4
             ORDER BY u.id ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':country', $countryCode);
        $stmt->bindValue(':region', $regionCode);
        $stmt->bindValue(':l4', $l4);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function broaderFallback(int $userId, string $countryCode, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id AS user_id
             FROM users u
             JOIN profiles p ON p.user_id = u.id
             WHERE u.status = 'active'
               AND u.id <> :uid
               AND p.country_code = :country
             ORDER BY u.id ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':country', $countryCode);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function activeGoalIds(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT goal_id FROM user_goals WHERE user_id = :uid AND status = 'active'");
        $stmt->execute(['uid' => $userId]);
        return array_map(static fn(array $r) => (int)$r['goal_id'], $stmt->fetchAll());
    }

    public function boundaries(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT boundary_key, boundary_value, importance FROM profile_boundaries WHERE user_id = :uid");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function availability(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT weekday, start_minute, end_minute FROM availability_slots WHERE user_id = :uid");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function isBlocked(int $userA, int $userB): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM blocks
             WHERE (blocker_user_id = :a AND blocked_user_id = :b)
                OR (blocker_user_id = :b AND blocked_user_id = :a)
             LIMIT 1"
        );
        $stmt->execute(['a' => $userA, 'b' => $userB]);
        return (bool)$stmt->fetchColumn();
    }

    public function upsertCandidateQueue(int $userId, int $candidateId, int $goalId, array $payload): void
    {
        if ($this->isSqlite()) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO match_candidate_queue
                    (user_id, candidate_user_id, goal_id, hard_filter_passed, compatibility_score, score_breakdown_json, rejection_reason_code, status, queued_at, processed_at, expires_at)
                 VALUES
                    (:uid, :cid, :goal, :passed, :score, :breakdown, :reason, :status, :queued, :processed, :expires)
                 ON CONFLICT(user_id, candidate_user_id, goal_id) DO UPDATE SET
                    hard_filter_passed = excluded.hard_filter_passed,
                    compatibility_score = excluded.compatibility_score,
                    score_breakdown_json = excluded.score_breakdown_json,
                    rejection_reason_code = excluded.rejection_reason_code,
                    status = excluded.status,
                    processed_at = excluded.processed_at,
                    expires_at = excluded.expires_at"
            );
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO match_candidate_queue
                    (user_id, candidate_user_id, goal_id, hard_filter_passed, compatibility_score, score_breakdown_json, rejection_reason_code, status, queued_at, processed_at, expires_at)
                 VALUES
                    (:uid, :cid, :goal, :passed, :score, :breakdown, :reason, :status, :queued, :processed, :expires)
                 ON DUPLICATE KEY UPDATE
                    hard_filter_passed = VALUES(hard_filter_passed),
                    compatibility_score = VALUES(compatibility_score),
                    score_breakdown_json = VALUES(score_breakdown_json),
                    rejection_reason_code = VALUES(rejection_reason_code),
                    status = VALUES(status),
                    processed_at = VALUES(processed_at),
                    expires_at = VALUES(expires_at)"
            );
        }

        $now = $this->now();

        $stmt->execute([
            'uid' => $userId,
            'cid' => $candidateId,
            'goal' => $goalId,
            'passed' => $payload['hard_filter_passed'] ? 1 : 0,
            'score' => $payload['compatibility_score'],
            'breakdown' => $payload['score_breakdown_json'],
            'reason' => $payload['rejection_reason_code'],
            'status' => $payload['status'],
            'queued' => $now,
            'processed' => $now,
            'expires' => $payload['expires_at'],
        ]);
    }

    public function deactivateActiveNoMatchStates(int $userId, ?int $goalId): void
    {
        $goalScope = $goalId ?? 0;
        $stmt = $this->pdo->prepare(
            "UPDATE no_match_states
             SET is_active = 0, updated_at = :updated
             WHERE user_id = :uid
               AND goal_scope_key = :goal_scope
               AND is_active = 1"
        );
        $stmt->execute(['uid' => $userId, 'goal_scope' => $goalScope, 'updated' => $this->now()]);
    }

    public function insertNoMatchState(int $userId, ?int $goalId, string $state, array $context): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO no_match_states
                (user_id, goal_id, goal_scope_key, state, context_json, is_active, next_recheck_at, notify_on_strong_match, created_at, updated_at)
             VALUES
                (:uid, :goal, :goal_scope, :state, :context, 1, :next_recheck, 1, :created, :updated)"
        );
        $now = $this->now();
        $stmt->execute([
            'uid' => $userId,
            'goal' => $goalId,
            'goal_scope' => $goalId ?? 0,
            'state' => $state,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'next_recheck' => date('Y-m-d H:i:s', strtotime($now . ' +1 day')),
            'created' => $now,
            'updated' => $now,
        ]);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }
}
