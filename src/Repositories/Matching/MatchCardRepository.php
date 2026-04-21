<?php

declare(strict_types=1);

namespace App\Repositories\Matching;

use PDO;

final class MatchCardRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function usersBatch(int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id AS user_id
             FROM users
             WHERE status = 'active'
             ORDER BY id ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function queuedRowsForCardBuild(int $viewerUserId, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, user_id, candidate_user_id, goal_id, compatibility_score, score_breakdown_json
             FROM match_candidate_queue
             WHERE user_id = :uid
               AND hard_filter_passed = 1
               AND compatibility_score IS NOT NULL
               AND status IN ('scored', 'presented')
               AND (expires_at IS NULL OR expires_at > :now)
             ORDER BY compatibility_score DESC, processed_at DESC, id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':uid', $viewerUserId, PDO::PARAM_INT);
        $stmt->bindValue(':now', date('Y-m-d H:i:s'));
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function userProfileSummary(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT p.user_id, p.birth_year, p.country_code, p.region_code, p.location_cell_l4, p.location_cell_l5,
                    p.communication_style, p.social_energy
             FROM profiles p
             JOIN users u ON u.id = p.user_id
             WHERE p.user_id = :uid AND u.status = 'active'
             LIMIT 1"
        );
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['boundaries'] = $this->userBoundaries($userId);

        return $row;
    }

    public function userBoundaries(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT boundary_key, boundary_value, importance
             FROM profile_boundaries
             WHERE user_id = :uid"
        );
        $stmt->execute(['uid' => $userId]);

        return $stmt->fetchAll();
    }

    public function upsertMatchFromQueue(int $viewerUserId, int $candidateUserId, int $goalId, float $score, array $explanation): int
    {
        $a = min($viewerUserId, $candidateUserId);
        $b = max($viewerUserId, $candidateUserId);

        $stmt = $this->pdo->prepare(
            "SELECT id, user_a_id, user_b_id, score_a_to_b, score_b_to_a
             FROM matches
             WHERE user_a_id = :a AND user_b_id = :b AND goal_id = :goal
             LIMIT 1"
        );
        $stmt->execute(['a' => $a, 'b' => $b, 'goal' => $goalId]);
        $existing = $stmt->fetch();

        $now = date('Y-m-d H:i:s');

        if (!$existing) {
            $scoreAToB = $viewerUserId === $a ? $score : 0.0;
            $scoreBToA = $viewerUserId === $b ? $score : 0.0;
            $mutual = round(($scoreAToB + $scoreBToA) / 2, 2);

            $insert = $this->pdo->prepare(
                "INSERT INTO matches
                    (user_a_id, user_b_id, goal_id, score_a_to_b, score_b_to_a, mutual_score, explanation_json, status, created_at, updated_at)
                 VALUES
                    (:a, :b, :goal, :score_a_to_b, :score_b_to_a, :mutual, :explanation, 'suggested', :created, :updated)"
            );
            $insert->execute([
                'a' => $a,
                'b' => $b,
                'goal' => $goalId,
                'score_a_to_b' => $scoreAToB,
                'score_b_to_a' => $scoreBToA,
                'mutual' => $mutual,
                'explanation' => json_encode($explanation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created' => $now,
                'updated' => $now,
            ]);

            return (int)$this->pdo->lastInsertId();
        }

        $scoreAToB = (float)$existing['score_a_to_b'];
        $scoreBToA = (float)$existing['score_b_to_a'];
        if ($viewerUserId === (int)$existing['user_a_id']) {
            $scoreAToB = $score;
        } else {
            $scoreBToA = $score;
        }
        $mutual = round(($scoreAToB + $scoreBToA) / 2, 2);

        $update = $this->pdo->prepare(
            "UPDATE matches
             SET score_a_to_b = :score_a_to_b,
                 score_b_to_a = :score_b_to_a,
                 mutual_score = :mutual,
                 explanation_json = :explanation,
                 updated_at = :updated
             WHERE id = :id"
        );
        $update->execute([
            'score_a_to_b' => $scoreAToB,
            'score_b_to_a' => $scoreBToA,
            'mutual' => $mutual,
            'explanation' => json_encode($explanation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated' => $now,
            'id' => (int)$existing['id'],
        ]);

        return (int)$existing['id'];
    }

    public function upsertViewerCard(int $matchId, int $viewerUserId, array $payload): void
    {
        $sql = "INSERT INTO match_cards
                (match_id, viewer_user_id, age_range_label_key, approx_distance_bucket, compatibility_score, emotional_summary_key, match_reasons_json, communication_boundaries_json, schedule_overlap_key, card_version, created_at, updated_at)
            VALUES
                (:match_id, :viewer_user_id, :age_range_label_key, :approx_distance_bucket, :compatibility_score, :emotional_summary_key, :match_reasons_json, :communication_boundaries_json, :schedule_overlap_key, :card_version, :created_at, :updated_at)";

        if ($this->isSqlite()) {
            $sql .= " ON CONFLICT(match_id, viewer_user_id) DO UPDATE SET
                age_range_label_key = excluded.age_range_label_key,
                approx_distance_bucket = excluded.approx_distance_bucket,
                compatibility_score = excluded.compatibility_score,
                emotional_summary_key = excluded.emotional_summary_key,
                match_reasons_json = excluded.match_reasons_json,
                communication_boundaries_json = excluded.communication_boundaries_json,
                schedule_overlap_key = excluded.schedule_overlap_key,
                card_version = excluded.card_version,
                updated_at = excluded.updated_at";
        } else {
            $sql .= " ON DUPLICATE KEY UPDATE
                age_range_label_key = VALUES(age_range_label_key),
                approx_distance_bucket = VALUES(approx_distance_bucket),
                compatibility_score = VALUES(compatibility_score),
                emotional_summary_key = VALUES(emotional_summary_key),
                match_reasons_json = VALUES(match_reasons_json),
                communication_boundaries_json = VALUES(communication_boundaries_json),
                schedule_overlap_key = VALUES(schedule_overlap_key),
                card_version = VALUES(card_version),
                updated_at = VALUES(updated_at)";
        }

        $stmt = $this->pdo->prepare($sql);
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            'match_id' => $matchId,
            'viewer_user_id' => $viewerUserId,
            'age_range_label_key' => $payload['age_range_label_key'],
            'approx_distance_bucket' => $payload['approx_distance_bucket'],
            'compatibility_score' => $payload['compatibility_score'],
            'emotional_summary_key' => $payload['emotional_summary_key'],
            'match_reasons_json' => json_encode($payload['match_reasons_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'communication_boundaries_json' => json_encode($payload['communication_boundaries_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'schedule_overlap_key' => $payload['schedule_overlap_key'],
            'card_version' => $payload['card_version'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function markQueuePresented(int $rowId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE match_candidate_queue
             SET status = 'presented', processed_at = :processed
             WHERE id = :id"
        );
        $stmt->execute(['id' => $rowId, 'processed' => date('Y-m-d H:i:s')]);
    }

    public function fetchDisplayableCards(int $viewerUserId, int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT mc.id, mc.match_id, mc.viewer_user_id,
                    mc.age_range_label_key, mc.approx_distance_bucket, mc.compatibility_score,
                    mc.emotional_summary_key, mc.match_reasons_json, mc.communication_boundaries_json,
                    mc.schedule_overlap_key, mc.card_version, mc.updated_at
             FROM match_cards mc
             JOIN matches m ON m.id = mc.match_id
             WHERE mc.viewer_user_id = :uid
               AND m.status IN ('suggested', 'interested_one_side', 'mutual', 'chat_open')
             ORDER BY mc.compatibility_score DESC, mc.updated_at DESC, mc.id DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':uid', $viewerUserId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['match_reasons_json'] = json_decode((string)$row['match_reasons_json'], true) ?? [];
            $row['communication_boundaries_json'] = json_decode((string)($row['communication_boundaries_json'] ?? '[]'), true) ?? [];
        }

        return $rows;
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }
}
