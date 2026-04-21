<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Repositories\Matching\CandidateRepository;
use Throwable;

final class CandidateGenerationService
{
    public function __construct(
        private readonly CandidateRepository $repo,
        private readonly HardFilterService $hardFilter,
        private readonly CompatibilityScoringService $scoring,
        private readonly ExplanationBuilderService $explanations,
        private readonly CandidateQueueService $queue,
        private readonly NoMatchStateService $noMatch,
    ) {}

    public function processUser(int $userId, int $candidateLimit = 200): void
    {
        $source = $this->repo->userContext($userId);
        if (!$source || ($source['status'] ?? '') !== 'active') {
            return;
        }

        $candidateIds = $this->stagedCandidateIds($source, $candidateLimit);
        $strong = 0;
        $primaryGoalId = $source['goals'][0] ?? null;

        foreach ($candidateIds as $candidateId) {
            try {
                $candidate = $this->repo->userContext($candidateId);
                if (!$candidate) continue;

                $hard = $this->hardFilter->evaluate($source, $candidate);
                $goalOverlap = $hard['goal_overlap'];

                if ($goalOverlap === [] && $primaryGoalId !== null) {
                    // keep rejection queue visibility under source primary goal
                    $goalOverlap = [(int)$primaryGoalId];
                }

                if (!$hard['eligible_for_display']) {
                    foreach ($goalOverlap as $goalId) {
                        $this->queue->writeRejected($userId, (int)$candidate['user_id'], (int)$goalId, $hard['rejection_reason_codes'][0]);
                    }
                    continue;
                }

                $score = $this->scoring->score($source, $candidate, $goalOverlap);
                $explanation = $this->explanations->build($score);

                foreach ($goalOverlap as $goalId) {
                    $this->queue->writeScored($userId, (int)$candidate['user_id'], (int)$goalId, (float)$score['compatibility_score'], $explanation);
                }

                if ((float)$score['compatibility_score'] >= 70.0) {
                    $strong++;
                }
            } catch (Throwable $e) {
                error_log(sprintf('[candidate_generation] %s user=%d candidate=%d msg=%s', $e::class, $userId, $candidateId, $e->getMessage()));
            }
        }

        $profileWeak = $this->isProfileWeak($source);
        $this->noMatch->update($userId, $primaryGoalId ? (int)$primaryGoalId : null, $strong, $profileWeak);
    }

    private function stagedCandidateIds(array $source, int $limit): array
    {
        $ids = [];

        $country = trim((string)($source['country_code'] ?? ''));
        $region = trim((string)($source['region_code'] ?? ''));
        $l5 = trim((string)($source['location_cell_l5'] ?? ''));
        $l4 = trim((string)($source['location_cell_l4'] ?? ''));

        if ($country !== '' && $region !== '' && $l5 !== '') {
            $strict = $this->repo->strictNearbyL5(
                (int)$source['user_id'],
                $country,
                $region,
                $l5,
                $limit
            );
            foreach ($strict as $row) $ids[(int)$row['user_id']] = true;
        }

        if (count($ids) < $limit && $country !== '' && $region !== '' && $l4 !== '') {
            $relaxed = $this->repo->relaxedNearbyL4(
                (int)$source['user_id'],
                $country,
                $region,
                $l4,
                $limit
            );
            foreach ($relaxed as $row) $ids[(int)$row['user_id']] = true;
        }

        if (count($ids) < $limit && $country !== '') {
            $fallback = $this->repo->broaderFallback(
                (int)$source['user_id'],
                $country,
                $limit
            );
            foreach ($fallback as $row) $ids[(int)$row['user_id']] = true;
        }

        return array_slice(array_keys($ids), 0, $limit);
    }

    private function isProfileWeak(array $source): bool
    {
        $weakText = empty($source['about_me']) || empty($source['looking_for']);
        $weakGoals = count($source['goals']) === 0;
        $weakSchedule = count($source['availability']) === 0;
        $weakDimensions = 0;
        foreach (['social_energy','communication_style','emotional_openness','relationship_pace','independence_level','boundary_sensitivity','structure_vs_spontaneity'] as $f) {
            if (empty($source[$f])) $weakDimensions++;
        }

        return $weakText || $weakGoals || $weakSchedule || $weakDimensions >= 4;
    }
}
