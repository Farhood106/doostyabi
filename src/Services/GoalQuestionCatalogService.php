<?php

declare(strict_types=1);

namespace App\Services;

final class GoalQuestionCatalogService
{
    /** @var string[] */
    private const SHARED_BASELINE_KEYS = [
        'must.boundary_respect',
        'must.discretion_required',
        'seek.age_min',
        'seek.age_max',
        'seek.gender',
        'seek.relationship_status',
        'seek.location_scope',
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
            'seek.body_type',
            'seek.height_preference',
            'seek.hair_preference',
            'seek.physical_attraction_importance',
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
            'seek.connection_expectation',
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

    /** @return array<int,array<string,mixed>> */
    public function fallbackDefinitionsForGoalSlug(string $goalSlug, int $goalId): array
    {
        $defs = match ($goalSlug) {
            'emotional_connection' => [
                $this->def($goalId, 'seek.emotional_climate', 'onboarding.goal_pref.seek_emotional_climate.label', 'onboarding.goal_pref.seek_emotional_climate.helper', 'select', ['calm', 'warm', 'deep'], 1),
                $this->def($goalId, 'offer.emotional_availability', 'onboarding.goal_pref.offer_emotional_availability.label', 'onboarding.goal_pref.offer_emotional_availability.helper', 'select', ['light_checkins', 'steady_support', 'high_presence'], 1),
                $this->def($goalId, 'pace.connection_speed', 'onboarding.goal_pref.pace_connection_speed.label', null, 'select', ['slow', 'moderate', 'fast'], 1),
                $this->def($goalId, 'duration.relationship_horizon', 'onboarding.goal_pref.duration_relationship_horizon.label', null, 'select', ['explore_1_3_months', 'mid_term', 'long_term_open'], 1),
                $this->def($goalId, 'accept.communication_style', 'onboarding.goal_pref.accept_communication_style.label', null, 'multiselect', ['text_short', 'voice', 'scheduled_calls', 'infrequent_ok'], 0),
            ],
            'long_term_relationship' => [
                $this->def($goalId, 'seek.connection_expectation', 'onboarding.goal_pref.seek_connection_expectation.label', null, 'select', ['serious_relationship', 'marriage_intent'], 1),
                $this->def($goalId, 'must.commitment_intent', 'onboarding.goal_pref.must_commitment_intent.label', null, 'select', ['explicit_long_term', 'long_term_preferred', 'open_but_unsure'], 1),
                $this->def($goalId, 'seek.relationship_status', 'onboarding.goal_pref.seek_relationship_status.label', null, 'select', ['single', 'separated', 'does_not_matter'], 0),
                $this->def($goalId, 'seek.body_type', 'onboarding.goal_pref.seek_body_type.label', null, 'select', ['slim','average','athletic','does_not_matter'], 0),
                $this->def($goalId, 'seek.stability_level', 'onboarding.goal_pref.seek_stability_level.label', null, 'select', ['steady', 'highly_structured', 'flexible_but_reliable'], 1),
                $this->def($goalId, 'offer.consistency_level', 'onboarding.goal_pref.offer_consistency_level.label', null, 'select', ['weekly_reliable', 'high_reliability', 'moderate_reliability'], 1),
                $this->def($goalId, 'pace.commitment_pace', 'onboarding.goal_pref.pace_commitment_pace.label', null, 'select', ['slow_intentional', 'moderate', 'fast_if_aligned'], 1),
            ],
            'travel_companion', 'event_companion', 'social_activity_partner' => [
                $this->def($goalId, 'seek.activity_type', 'onboarding.goal_pref.seek_activity_type.label', null, 'multiselect', ['city_walk', 'museum', 'food_explore', 'hiking', 'road_trip'], 1),
                $this->def($goalId, 'offer.planning_style', 'onboarding.goal_pref.offer_planning_style.label', null, 'select', ['structured', 'hybrid', 'spontaneous'], 1),
                $this->def($goalId, 'accept.budget_band', 'onboarding.goal_pref.accept_budget_band.label', null, 'select', ['low', 'medium', 'high', 'mixed_by_plan'], 1),
                $this->def($goalId, 'must.public_meet_first', 'onboarding.goal_pref.must_public_meet_first.label', null, 'select', ['required', 'not_required'], 1),
            ],
            'sports_companion' => [
                $this->def($goalId, 'seek.activity_intensity', 'onboarding.goal_pref.seek_activity_intensity.label', null, 'select', ['light', 'moderate', 'high'], 1),
                $this->def($goalId, 'offer.activity_intensity', 'onboarding.goal_pref.offer_activity_intensity.label', null, 'select', ['light', 'moderate', 'high'], 1),
                $this->def($goalId, 'pace.session_cadence', 'onboarding.goal_pref.pace_session_cadence.label', null, 'select', ['weekly', '2_3_week', 'daily'], 1),
                $this->def($goalId, 'must.safety_boundary_respect', 'onboarding.goal_pref.must_safety_boundary_respect.label', null, 'select', ['required', 'not_sure'], 1),
            ],
            'project_collaboration', 'co_living' => [
                $this->def($goalId, 'seek.partner_role', 'onboarding.goal_pref.seek_partner_role.label', null, 'multiselect', ['planner', 'builder', 'designer', 'operator', 'researcher'], 1),
                $this->def($goalId, 'offer.role', 'onboarding.goal_pref.offer_role.label', null, 'multiselect', ['planner', 'builder', 'designer', 'operator', 'researcher'], 1),
                $this->def($goalId, 'accept.collab_format', 'onboarding.goal_pref.accept_collab_format.label', null, 'multiselect', ['async', 'weekly_sync', 'daily_sync'], 1),
                $this->def($goalId, 'must.commitment_reliability', 'onboarding.goal_pref.must_commitment_reliability.label', null, 'select', ['required', 'not_sure'], 1),
            ],
            'casual_connection' => [
                $this->def($goalId, 'seek.connection_expectation', 'onboarding.goal_pref.seek_connection_expectation.label', null, 'select', ['light_chat', 'companionship', 'low_commitment_romantic'], 1),
                $this->def($goalId, 'seek.body_type', 'onboarding.goal_pref.seek_body_type.label', null, 'select', ['slim','average','curvy','plus_size','athletic','does_not_matter'], 0),
                $this->def($goalId, 'seek.height_preference', 'onboarding.goal_pref.seek_height_preference.label', null, 'select', ['shorter','average','tall','does_not_matter'], 0),
                $this->def($goalId, 'seek.hair_preference', 'onboarding.goal_pref.seek_hair_preference.label', null, 'select', ['dark','light','red','does_not_matter'], 0),
                $this->def($goalId, 'seek.physical_attraction_importance', 'onboarding.goal_pref.seek_physical_attraction_importance.label', null, 'select', ['important','somewhat','not_important'], 0),
                $this->def($goalId, 'accept.emotional_involvement_level', 'onboarding.goal_pref.accept_emotional_involvement_level.label', null, 'select', ['low', 'moderate'], 1),
                $this->def($goalId, 'pace.intimacy_pace', 'onboarding.goal_pref.pace_intimacy_pace.label', null, 'select', ['very_slow', 'slow', 'mutually_set'], 1),
                $this->def($goalId, 'must.consent_style', 'onboarding.goal_pref.must_consent_style.label', null, 'select', ['affirmative_and_ongoing', 'not_sure'], 1),
                $this->def($goalId, 'duration.connection_window', 'onboarding.goal_pref.duration_connection_window.label', null, 'select', ['few_weeks', '1_3_months', 'open_ended_light'], 1),
                $this->def($goalId, 'accept.appearance_preference_optional_general', 'onboarding.goal_pref.accept_appearance_preference_optional_general.label', null, 'text', [], 0),
            ],
            'support_based_relationship' => [
                $this->def($goalId, 'seek.connection_expectation', 'onboarding.goal_pref.seek_connection_expectation.label', null, 'select', ['supportive_clear','respectful_non_committed'], 1),
                $this->def($goalId, 'seek.support_role', 'onboarding.goal_pref.seek_support_role.label', null, 'select', ['seek_support', 'flexible'], 1),
                $this->def($goalId, 'seek.support_type', 'onboarding.goal_pref.seek_support_type.label', null, 'multiselect', ['mentorship', 'practical_help', 'lifestyle_support'], 1),
                $this->def($goalId, 'offer.support_role', 'onboarding.goal_pref.offer_support_role.label', null, 'select', ['offer_support', 'flexible'], 1),
                $this->def($goalId, 'offer.support_type', 'onboarding.goal_pref.offer_support_type.label', null, 'multiselect', ['mentorship', 'practical_help', 'lifestyle_support'], 1),
                $this->def($goalId, 'must.transparency_level', 'onboarding.goal_pref.must_transparency_level.label', null, 'select', ['required', 'not_sure'], 1),
                $this->def($goalId, 'duration.arrangement_horizon', 'onboarding.goal_pref.duration_arrangement_horizon.label', null, 'select', ['1_month', '3_months', '6_months_plus'], 1),
            ],
            default => [
                $this->def($goalId, 'seek.social_energy', 'onboarding.goal_pref.seek_social_energy.label', null, 'select', ['quiet_low_pressure', 'balanced', 'high_energy'], 0),
                $this->def($goalId, 'offer.social_energy', 'onboarding.goal_pref.offer_social_energy.label', null, 'select', ['calm_listener', 'balanced', 'high_energy_initiator'], 0),
                $this->def($goalId, 'duration.friendship_intent', 'onboarding.goal_pref.duration_friendship_intent.label', null, 'select', ['explore', 'ongoing'], 1),
            ],
        };

        return $defs;
    }

    /** @param string[] $allowed */
    private function def(int $goalId, string $prefKey, string $labelKey, ?string $helperTextKey, string $inputType, array $allowed, int $required): array
    {
        return [
            'id' => 0,
            'goal_id' => $goalId,
            'pref_key' => $prefKey,
            'label_key' => $labelKey,
            'helper_text_key' => $helperTextKey,
            'input_type' => $inputType,
            'value_type' => $inputType === 'multiselect' ? 'json' : 'string',
            'allowed_values_json' => json_encode($allowed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'allowed_values' => $allowed,
            'is_required' => $required,
            'is_active' => 1,
        ];
    }

    public function fallbackFaOptionLabel(string $prefKey, string $value): string
    {
        $map = [
            'calm' => 'آرام', 'warm' => 'گرم', 'deep' => 'عمیق',
            'light_checkins' => 'پیگیری سبک', 'steady_support' => 'حمایت پایدار', 'high_presence' => 'حضور بالا',
            'slow' => 'آهسته', 'moderate' => 'متوسط', 'fast' => 'سریع',
            'explore_1_3_months' => 'آزمایشی ۱ تا ۳ ماه', 'mid_term' => 'میان‌مدت', 'long_term_open' => 'باز برای بلندمدت',
            'city_walk' => 'پیاده‌روی شهری', 'museum' => 'موزه', 'food_explore' => 'گردش غذایی', 'hiking' => 'طبیعت‌گردی', 'road_trip' => 'سفر جاده‌ای',
            'structured' => 'برنامه‌ریزی‌شده', 'hybrid' => 'ترکیبی', 'spontaneous' => 'خودجوش',
            'required' => 'الزامی', 'not_required' => 'الزامی نیست', 'not_sure' => 'مطمئن نیستم',
            'light' => 'سبک', 'high' => 'زیاد', 'weekly' => 'هفتگی', '2_3_week' => '۲-۳ بار در هفته', 'daily' => 'روزانه',
            'planner' => 'برنامه‌ریز', 'builder' => 'سازنده', 'designer' => 'طراح', 'operator' => 'مجری', 'researcher' => 'پژوهشگر',
            'async' => 'غیرهمزمان', 'weekly_sync' => 'همگام‌سازی هفتگی', 'daily_sync' => 'همگام‌سازی روزانه',
            'light_chat' => 'گفت‌وگوی سبک', 'companionship' => 'همراهی', 'low_commitment_romantic' => 'رابطه کم‌تعهد',
            'very_slow' => 'خیلی آهسته', 'mutually_set' => 'با توافق طرفین',
            'affirmative_and_ongoing' => 'رضایت صریح و مستمر',
            'few_weeks' => 'چند هفته', '1_3_months' => '۱ تا ۳ ماه', 'open_ended_light' => 'باز و سبک',
            'seek_support' => 'نیازمند حمایت', 'offer_support' => 'ارائه‌دهنده حمایت', 'flexible' => 'منعطف',
            'mentorship' => 'منتورینگ', 'practical_help' => 'کمک عملی', 'lifestyle_support' => 'حمایت سبک زندگی',
            '1_month' => '۱ ماه', '3_months' => '۳ ماه', '6_months_plus' => '۶ ماه به بالا',
        ];
        return $map[$value] ?? str_replace('_', ' ', $value);
    }
}
