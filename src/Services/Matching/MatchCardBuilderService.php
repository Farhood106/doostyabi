<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Repositories\Matching\MatchCardRepository;

final class MatchCardBuilderService
{
    public function __construct(
        private readonly MatchCardRepository $repo,
        private readonly AgeLabelBuilderService $ageLabel,
        private readonly DistanceBucketService $distanceBucket,
        private readonly EmotionalSummaryBuilderService $emotionalSummary,
        private readonly int $cardVersion = 1,
    ) {}

    public function buildOrRefreshForUser(int $viewerUserId, int $limit = 100): int
    {
        $rows = $this->repo->queuedRowsForCardBuild($viewerUserId, $limit);
        if ($rows === []) {
            return 0;
        }

        $viewer = $this->repo->userProfileSummary($viewerUserId);
        if (!$viewer) {
            return 0;
        }

        $count = 0;
        foreach ($rows as $row) {
            $candidate = $this->repo->userProfileSummary((int)$row['candidate_user_id']);
            if (!$candidate) {
                continue;
            }

            $breakdownPayload = json_decode((string)($row['score_breakdown_json'] ?? '{}'), true) ?? [];
            $scoreBreakdown = (array)($breakdownPayload['score_breakdown'] ?? []);
            $topReasons = array_values((array)($breakdownPayload['top_match_reasons'] ?? []));

            $payload = [
                'age_range_label_key' => $this->ageLabel->fromBirthYear(isset($candidate['birth_year']) ? (int)$candidate['birth_year'] : null),
                'approx_distance_bucket' => $this->distanceBucket->fromProfiles($viewer, $candidate),
                'compatibility_score' => (float)$row['compatibility_score'],
                'emotional_summary_key' => $this->emotionalSummary->buildKey($scoreBreakdown, $viewer, $candidate),
                'match_reasons_json' => $this->safeReasons($topReasons),
                'communication_boundaries_json' => $this->safeCommunicationBoundaries((array)$candidate['boundaries']),
                'schedule_overlap_key' => $this->scheduleOverlapKey((float)($scoreBreakdown['schedule_overlap'] ?? 0)),
                'card_version' => $this->cardVersion,
            ];

            $matchId = $this->repo->upsertMatchFromQueue(
                $viewerUserId,
                (int)$row['candidate_user_id'],
                (int)$row['goal_id'],
                (float)$row['compatibility_score'],
                [
                    'source' => 'match_card_builder',
                    'score_breakdown' => $scoreBreakdown,
                    'top_match_reasons' => $payload['match_reasons_json'],
                    'emotional_summary_key' => $payload['emotional_summary_key'],
                ]
            );

            $this->repo->upsertViewerCard($matchId, $viewerUserId, $payload);
            $this->repo->markQueuePresented((int)$row['id']);
            $count++;
        }

        return $count;
    }

    private function safeReasons(array $reasons): array
    {
        $safe = [];
        foreach ($reasons as $reason) {
            $reason = (string)$reason;
            if (str_starts_with($reason, 'explanation.')) {
                $safe[] = $reason;
            }
        }

        return array_slice(array_values(array_unique($safe)), 0, 4);
    }

    private function safeCommunicationBoundaries(array $boundaries): array
    {
        $safe = [];
        foreach ($boundaries as $row) {
            $key = (string)($row['boundary_key'] ?? '');
            $value = (string)($row['boundary_value'] ?? '');
            $importance = strtolower((string)($row['importance'] ?? ''));
            if ($key === '' || $value === '') {
                continue;
            }
            if (!in_array($importance, ['required', 'avoid'], true)) {
                continue;
            }

            // Privacy-safe: include abstract preference tags only, never identity fields.
            if (in_array($key, ['name', 'phone', 'email', 'address', 'instagram', 'telegram', 'photo'], true)) {
                continue;
            }

            $safe[] = [
                'key' => $key,
                'value' => $value,
                'importance' => $importance,
            ];
        }

        return array_slice($safe, 0, 8);
    }

    private function scheduleOverlapKey(float $scheduleOverlap): string
    {
        if ($scheduleOverlap >= 70) {
            return 'schedule_overlap_high';
        }
        if ($scheduleOverlap >= 40) {
            return 'schedule_overlap_medium';
        }

        return 'schedule_overlap_low';
    }
}
