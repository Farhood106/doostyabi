<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OnboardingRepository;

final class OnboardingProgressService
{
    public function __construct(private readonly OnboardingRepository $repo) {}

    public function canAccessStep(int $userId, string $step): bool
    {
        $state = $this->repo->completionState($userId);

        return match ($step) {
            'profile' => true,
            'boundaries' => $state['profile'],
            'availability' => $state['profile'] && $state['boundaries'],
            'goals' => $state['profile'] && $state['boundaries'] && $state['availability'],
            default => false,
        };
    }

    public function firstIncompleteStep(int $userId): string
    {
        $state = $this->repo->completionState($userId);

        if (!$state['profile']) return 'profile';
        if (!$state['boundaries']) return 'boundaries';
        if (!$state['availability']) return 'availability';
        if (!$state['goals']) return 'goals';

        return 'done';
    }
}
