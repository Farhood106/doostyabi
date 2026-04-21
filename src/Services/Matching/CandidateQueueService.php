<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Repositories\Matching\CandidateRepository;

final class CandidateQueueService
{
    public function __construct(private readonly CandidateRepository $repo) {}

    public function writeRejected(int $userId, int $candidateId, int $goalId, string $reasonCode): void
    {
        $this->repo->upsertCandidateQueue($userId, $candidateId, $goalId, [
            'hard_filter_passed' => false,
            'compatibility_score' => null,
            'score_breakdown_json' => null,
            'rejection_reason_code' => $reasonCode,
            'status' => 'rejected',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
        ]);
    }

    public function writeScored(int $userId, int $candidateId, int $goalId, float $score, array $breakdown): void
    {
        $status = $score >= 65 ? 'scored' : 'rejected';
        $reason = $score >= 65 ? null : 'low_compatibility_score';

        $this->repo->upsertCandidateQueue($userId, $candidateId, $goalId, [
            'hard_filter_passed' => true,
            'compatibility_score' => $score,
            'score_breakdown_json' => json_encode($breakdown, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'rejection_reason_code' => $reason,
            'status' => $status,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);
    }
}
