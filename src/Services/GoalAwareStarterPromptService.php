<?php

declare(strict_types=1);

namespace App\Services;

final class GoalAwareStarterPromptService
{
    /** @return string[] */
    public function promptKeysForGoalSlug(?string $goalSlug): array
    {
        $cluster = $this->clusterForGoalSlug($goalSlug);

        return match ($cluster) {
            'emotional' => [
                'chat.starter.goal.emotional.1',
                'chat.starter.goal.emotional.2',
                'chat.starter.goal.emotional.3',
            ],
            'activity' => [
                'chat.starter.goal.activity.1',
                'chat.starter.goal.activity.2',
                'chat.starter.goal.activity.3',
            ],
            'collaboration' => [
                'chat.starter.goal.collaboration.1',
                'chat.starter.goal.collaboration.2',
                'chat.starter.goal.collaboration.3',
            ],
            default => [
                'chat.starter_prompt.1',
                'chat.starter_prompt.2',
                'chat.starter_prompt.3',
            ],
        };
    }

    private function clusterForGoalSlug(?string $goalSlug): string
    {
        $slug = trim((string)$goalSlug);
        return match ($slug) {
            'emotional_connection', 'long_term_relationship', 'friendly_conversation', 'casual_connection', 'personal_growth_connection' => 'emotional',
            'travel_companion', 'social_activity_partner', 'event_companion', 'sports_companion' => 'activity',
            'project_collaboration', 'co_living' => 'collaboration',
            default => 'general',
        };
    }
}
