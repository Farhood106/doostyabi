<?php

declare(strict_types=1);

namespace App\Services;

final class GoalQuestionCatalogService
{
    /** @var string[] */
    private const SHARED_BASELINE_KEYS = [
        'must.boundary_respect',
        'privacy.discretion_level',
        'pace.response_cadence',
        'involvement.time_intensity',
    ];

    /** @var array<string,string[]> */
    private const PRIMARY_GOAL_KEYS_BY_SLUG = [
        'friendly_conversation' => [
            'seek.social_energy',
            'offer.social_energy',
            'accept.topic_scope',
            'duration.friendship_intent',
        ],
        'emotional_connection' => [
            'seek.emotional_climate',
            'offer.emotional_availability',
            'accept.communication_style',
            'pace.connection_speed',
            'duration.relationship_horizon',
        ],
        'long_term_relationship' => [
            'must.commitment_intent',
            'seek.stability_level',
            'offer.consistency_level',
            'pace.commitment_pace',
            'accept.conflict_style',
        ],
        'travel_companion' => [
            'seek.activity_type',
            'offer.planning_style',
            'accept.budget_band',
            'must.public_meet_first',
            'pace.trip_decision_speed',
            'duration.trip_window',
        ],
        'event_companion' => [
            'seek.activity_type',
            'offer.planning_style',
            'accept.budget_band',
            'must.public_meet_first',
            'pace.trip_decision_speed',
            'duration.trip_window',
        ],
        'social_activity_partner' => [
            'seek.activity_type',
            'offer.planning_style',
            'accept.budget_band',
            'must.public_meet_first',
            'pace.trip_decision_speed',
            'duration.trip_window',
        ],
        'sports_companion' => [
            'seek.activity_intensity',
            'offer.activity_intensity',
            'pace.session_cadence',
            'must.safety_boundary_respect',
            'duration.wellness_horizon',
        ],
        'project_collaboration' => [
            'seek.partner_role',
            'offer.role',
            'accept.collab_format',
            'must.commitment_reliability',
            'pace.decision_cadence',
            'duration.project_horizon',
        ],
        'co_living' => [
            'seek.partner_role',
            'offer.role',
            'accept.collab_format',
            'must.commitment_reliability',
            'pace.decision_cadence',
            'duration.project_horizon',
        ],
        'casual_connection' => [
            'seek.connection_expectation',
            'accept.emotional_involvement_level',
            'pace.intimacy_pace',
            'must.consent_style',
            'accept.appearance_preference_optional_general',
            'duration.connection_window',
        ],
        'personal_growth_connection' => [
            'seek.emotional_climate',
            'offer.emotional_availability',
            'pace.connection_speed',
            'duration.relationship_horizon',
        ],
        // optional new slug compatibility
        'support_based_relationship' => [
            'seek.support_role',
            'seek.support_type',
            'offer.support_role',
            'offer.support_type',
            'accept.support_expectation_clarity',
            'must.transparency_level',
            'pace.arrangement_setup_speed',
            'duration.arrangement_horizon',
        ],
    ];

    /** @var string[] */
    private const SENSITIVE_GOAL_SLUGS = [
        'casual_connection',
        'support_based_relationship',
    ];

    /** @var string[] */
    private const SENSITIVE_STRICT_PREF_KEYS = [
        'must.consent_style',
        'must.boundary_respect',
        'privacy.discretion_level',
        'must.transparency_level',
    ];

    /** @return string[] */
    public function orderedKeysForPrimaryGoalSlug(string $goalSlug): array
    {
        $specific = self::PRIMARY_GOAL_KEYS_BY_SLUG[$goalSlug] ?? [];
        return array_values(array_unique(array_merge(self::SHARED_BASELINE_KEYS, $specific)));
    }

    /** @return string[] */
    public function allowedPreferenceKeysForGoalSlug(string $goalSlug): array
    {
        return $this->orderedKeysForPrimaryGoalSlug($goalSlug);
    }

    public function isSensitiveGoalSlug(string $goalSlug): bool
    {
        return in_array($goalSlug, self::SENSITIVE_GOAL_SLUGS, true);
    }

    public function isStrictSensitiveKey(string $prefKey): bool
    {
        return in_array($prefKey, self::SENSITIVE_STRICT_PREF_KEYS, true);
    }
}
