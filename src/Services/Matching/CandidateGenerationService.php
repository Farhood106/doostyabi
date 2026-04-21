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

        $candidates = $this->repo->nearbyCandidates(
            $userId,
            (string)$source['country_code'],
            (string)$source['region_code'],
            (string)$source['location_cell_l4'],
            $candidateLimit
        );

        if ($candidates === []) {
            $candidates = $this->repo->fallbackCandidates($userId, $candidateLimit);
        }

        $strong = 0;
        $goalId = $source['goals'][0] ?? null;

        foreach ($candidates as $candidateRow) {
            try {
                $candidate = $this->repo->userContext((int)$candidateRow['user_id']);
                if (!$candidate) continue;

                $hard = $this->hardFilter->evaluate($source, $candidate);
                $goalOverlap = $hard['goal_overlap'];
                $activeGoalId = $goalOverlap[0] ?? $goalId;
                if ($activeGoalId === null) {
                    continue;
                }

                if (!$hard['eligible_for_display']) {
                    $this->queue->writeRejected($userId, (int)$candidate['user_id'], (int)$activeGoalId, $hard['rejection_reason_codes'][0]);
                    continue;
                }

                $score = $this->scoring->score($source, $candidate, $goalOverlap);
                $explanation = $this->explanations->build($score);
                $this->queue->writeScored($userId, (int)$candidate['user_id'], (int)$activeGoalId, (float)$score['compatibility_score'], $explanation);

                if ((float)$score['compatibility_score'] >= 70.0) {
                    $strong++;
                }
            } catch (Throwable $e) {
                error_log(sprintf('[candidate_generation] %s user=%d candidate=%d msg=%s', $e::class, $userId, (int)$candidateRow['user_id'], $e->getMessage()));
            }
        }

        $profileWeak = empty($source['about_me']) || empty($source['looking_for']);
        $this->noMatch->update($userId, $goalId ? (int)$goalId : null, $strong, $profileWeak);
    }
}
