<?php

declare(strict_types=1);

namespace App\Repositories\Matching;

use PDO;

final class MatchInterestRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function matchParticipants(int $matchId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, user_a_id, user_b_id, status FROM matches WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $matchId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function canonicalMatchIdForPair(int $userAId, int $userBId): ?int
    {
        $a = min($userAId, $userBId);
        $b = max($userAId, $userBId);
        $stmt = $this->pdo->prepare(
            "SELECT m.id
             FROM matches m
             LEFT JOIN chats c ON c.match_id = m.id
             WHERE m.user_a_id = :a AND m.user_b_id = :b
             ORDER BY
               CASE
                 WHEN m.status = 'chat_open' THEN 0
                 WHEN c.id IS NOT NULL THEN 1
                 WHEN m.status = 'mutual' THEN 2
                 WHEN m.status = 'interested_one_side' THEN 3
                 ELSE 4
               END ASC,
               m.mutual_score DESC,
               m.updated_at DESC,
               m.id DESC
             LIMIT 1"
        );
        $stmt->execute(['a' => $a, 'b' => $b]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int)$id;
    }

    public function appendAction(int $matchId, int $actorUserId, string $action): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO match_interest_actions (match_id, actor_user_id, action, metadata_json, created_at)
             VALUES (:match_id, :actor, :action, NULL, :created)'
        );
        $stmt->execute([
            'match_id' => $matchId,
            'actor' => $actorUserId,
            'action' => $action,
            'created' => date('Y-m-d H:i:s'),
        ]);
    }

    public function upsertInterestState(int $matchId, int $userId, string $currentInterest): void
    {
        $sql = 'INSERT INTO match_interest_states (match_id, user_id, current_interest, updated_at)
                VALUES (:match_id, :user_id, :interest, :updated_at)';

        if ($this->isSqlite()) {
            $sql .= ' ON CONFLICT(match_id, user_id) DO UPDATE SET
                        current_interest = excluded.current_interest,
                        updated_at = excluded.updated_at';
        } else {
            $sql .= ' ON DUPLICATE KEY UPDATE
                        current_interest = VALUES(current_interest),
                        updated_at = VALUES(updated_at)';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'match_id' => $matchId,
            'user_id' => $userId,
            'interest' => $currentInterest,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function interestStatesByMatch(int $matchId): array
    {
        $stmt = $this->pdo->prepare('SELECT user_id, current_interest FROM match_interest_states WHERE match_id = :mid');
        $stmt->execute(['mid' => $matchId]);

        return $stmt->fetchAll();
    }

    public function updateMatchStatus(int $matchId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE matches SET status = :status, updated_at = :updated WHERE id = :id');
        $stmt->execute(['status' => $status, 'updated' => date('Y-m-d H:i:s'), 'id' => $matchId]);
    }

    public function ensureChatExists(int $matchId): int
    {
        $existing = $this->chatByMatchId($matchId);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO chats (match_id, status, opened_at)
             VALUES (:match_id, :status, :opened_at)'
        );
        $stmt->execute([
            'match_id' => $matchId,
            'status' => 'open',
            'opened_at' => date('Y-m-d H:i:s'),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function chatByMatchId(int $matchId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM chats WHERE match_id = :mid LIMIT 1');
        $stmt->execute(['mid' => $matchId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int)$id;
    }

    public function syncPairStatusToCanonical(int $canonicalMatchId, int $userAId, int $userBId, string $status): void
    {
        $a = min($userAId, $userBId);
        $b = max($userAId, $userBId);
        $stmt = $this->pdo->prepare(
            'UPDATE matches
             SET status = :status, updated_at = :updated
             WHERE user_a_id = :a AND user_b_id = :b AND id <> :canonical_id'
        );
        $stmt->execute([
            'status' => $status,
            'updated' => date('Y-m-d H:i:s'),
            'a' => $a,
            'b' => $b,
            'canonical_id' => $canonicalMatchId,
        ]);
    }

    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }
}
