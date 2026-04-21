<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Repositories\Matching\CandidateRepository;

final class NoMatchStateService
{
    public function __construct(private readonly CandidateRepository $repo) {}

    public function update(int $userId, ?int $goalId, int $strongCount, bool $profileWeak): void
    {
        if ($strongCount > 0) {
            // Strong candidates exist: user should not remain in an active no-match state.
            $this->repo->deactivateActiveNoMatchStates($userId, $goalId);
            return;
        }

        $this->repo->deactivateActiveNoMatchStates($userId, $goalId);

        $state = $profileWeak ? 'profile_improvement_suggested' : 'expand_preferences_suggested';
        $this->repo->insertNoMatchState($userId, $goalId, $state, [
            'strong_candidates' => 0,
            'suggestion_key' => $profileWeak ? 'empty_state.improve_profile' : 'empty_state.expand_preferences',
        ]);
    }
}
