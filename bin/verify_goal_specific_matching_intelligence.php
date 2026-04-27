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
giAssert(in_array('privacy_importance', $casualKeys, true), 'casual catalog should include privacy importance');
giAssert(in_array('discretion_need', $casualKeys, true), 'casual catalog should include discretion need');

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
        10 => ['privacy_importance' => 'high', 'expectation_clarity' => 'clear', 'comfort_level' => 'balanced'],
    ],
];
$baseB = $baseA;
$baseB['gender_identity'] = 'man';

$aligned = $service->score($baseA, $baseB, [10]);
giAssert(((float)$aligned['score_breakdown']['goal_specific_fit']) >= 90.0, 'aligned goal preferences should score high in goal_specific_fit');
giAssert(in_array('explanation.goal_specific_alignment_good', $aligned['top_match_reasons'], true), 'aligned preferences should add goal-specific positive reason');

$mismatchB = $baseB;
$mismatchB['goal_preferences'][10] = ['privacy_importance' => 'low', 'expectation_clarity' => 'gradual', 'comfort_level' => 'open'];
$mismatch = $service->score($baseA, $mismatchB, [10]);
giAssert(((float)$mismatch['score_breakdown']['goal_specific_fit']) < ((float)$aligned['score_breakdown']['goal_specific_fit']), 'mismatch should reduce goal_specific_fit');
giAssert(count((array)$mismatch['score_breakdown']['preference_gaps']) >= 1, 'mismatch should expose preference gaps');

echo "OK: goal-specific matching intelligence verification passed\n";
