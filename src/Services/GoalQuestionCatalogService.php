<?php

declare(strict_types=1);

namespace App\Services;

final class GoalQuestionCatalogService
{
    private const PREF_KEYS_BY_CLUSTER = [
        'emotional_long_term' => [
            'relationship_pace',
            'emotional_availability',
            'commitment_expectation',
            'communication_depth',
            'independence_need',
            'conflict_style',
        ],
        'travel_activity_event' => [
            'travel_activity_style',
            'budget_comfort',
            'planning_style',
            'preferred_setting',
            'schedule_flexibility',
            'group_mode',
        ],
        'sports_wellness' => [
            'activity_intensity',
            'sport_type',
            'activity_goal',
            'activity_frequency',
            'indoor_outdoor_preference',
        ],
        'collaboration_project' => [
            'collab_role',
            'work_style',
            'seriousness_level',
            'time_commitment',
            'communication_cadence',
        ],
        'casual_respectful' => [
            'expectation_clarity',
            'intimacy_pace',
            'privacy_importance',
            'appearance_preference_general',
            'boundaries_importance',
            'discretion_need',
            'comfort_level',
        ],
        'friendship_general' => [
            'conversation_style',
            'preferred_topics',
            'social_energy_pref',
            'response_pace_pref',
            'meeting_comfort',
        ],
    ];

    public function clusterForGoalSlug(string $goalSlug): string
    {
        return match ($goalSlug) {
            'long_term_relationship', 'emotional_connection' => 'emotional_long_term',
            'travel_companion', 'social_activity_partner', 'event_companion' => 'travel_activity_event',
            'sports_companion' => 'sports_wellness',
            'project_collaboration' => 'collaboration_project',
            'casual_connection' => 'casual_respectful',
            default => 'friendship_general',
        };
    }

    /** @return string[] */
    public function allowedPreferenceKeysForGoalSlug(string $goalSlug): array
    {
        $cluster = $this->clusterForGoalSlug($goalSlug);
        return self::PREF_KEYS_BY_CLUSTER[$cluster] ?? [];
    }
}
