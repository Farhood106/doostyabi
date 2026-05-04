#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Services\GoalQuestionCatalogService;
use App\Services\Matching\CompatibilityScoringService;

require __DIR__ . '/../bootstrap/autoload.php';

function giAssert(bool $ok, string $msg): void
{
    if (!$ok) {
        throw new RuntimeException("Assertion failed: {$msg}");
    }
}

$catalog = new GoalQuestionCatalogService();
$casualKeys = $catalog->allowedPreferenceKeysForGoalSlug('casual_connection');
giAssert(in_array('must.discretion_required', $casualKeys, true), 'casual catalog should include discretion requirement');
giAssert(in_array('must.consent_style', $casualKeys, true), 'casual catalog should include consent style');

$service = new CompatibilityScoringService();
$baseA = [
    'availability' => [['weekday' => 1, 'start_minute' => 600, 'end_minute' => 900]],
    'social_energy' => 3,
    'communication_style' => 3,
    'emotional_openness' => 3,
    'relationship_pace' => 3,
    'independence_level' => 3,
    'boundary_sensitivity' => 4,
    'structure_vs_spontaneity' => 3,
    'smoking_preference' => 'no',
    'drinking_preference' => 'no',
    'activity_level' => 'moderate',
    'country_code' => 'IR',
    'region_code' => 'THR',
    'location_cell_l4' => 'THR',
    'location_cell_l5' => 'THR5',
    'interested_in_gender' => '',
    'gender_identity' => 'woman',
    'goal_preferences' => [
        10 => [
            'privacy.discretion_level' => 'high_preferred',
            'seek.connection_type' => 'companionship,light_chat',
            'offer.connection_type' => 'companionship',
            'accept.emotional_involvement_level' => 'low',
        ],
    ],
];
$baseB = $baseA;
$baseB['gender_identity'] = 'man';
$baseB['goal_preferences'][10]['seek.connection_type'] = 'companionship';
$baseB['goal_preferences'][10]['offer.connection_type'] = 'companionship,light_chat';

$aligned = $service->score($baseA, $baseB, [10]);
giAssert(((float)$aligned['score_breakdown']['need_offer_fit']) >= 70.0, 'aligned need/offer should score high');
giAssert(in_array('explanation.need_offer_alignment', $aligned['top_match_reasons'], true), 'aligned preferences should add need/offer reason');

$mismatchB = $baseB;
$mismatchB['goal_preferences'][10] = [
    'privacy.discretion_level' => 'strict_high_required',
    'seek.connection_type' => 'light_chat',
    'offer.connection_type' => 'low_commitment_romantic',
    'accept.emotional_involvement_level' => 'moderate',
];
$mismatch = $service->score($baseA, $mismatchB, [10]);
giAssert(((float)$mismatch['score_breakdown']['need_offer_fit']) < ((float)$aligned['score_breakdown']['need_offer_fit']), 'mismatch should reduce need_offer_fit');
giAssert(count((array)$mismatch['score_breakdown']['preference_gaps']) >= 1, 'mismatch should expose preference gaps');
giAssert(isset($mismatch['score_breakdown']['goal_fit']), 'score breakdown should include goal_fit');
giAssert(isset($mismatch['score_breakdown']['location_fit']), 'score breakdown should include location_fit');

echo "OK: goal-specific matching intelligence verification passed\n";
