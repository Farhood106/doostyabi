#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Repositories\OnboardingRepository;

require __DIR__ . '/../bootstrap/autoload.php';

function oassert(bool $ok, string $msg): void
{
    if (!$ok) {
        throw new RuntimeException("Assertion failed: {$msg}");
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));

$pdo->exec('CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, profile_completed_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE profile_boundaries (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, boundary_key TEXT, boundary_value TEXT, importance TEXT, created_at TEXT, updated_at TEXT, UNIQUE(user_id, boundary_key, boundary_value))');
$pdo->exec('CREATE TABLE availability_slots (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER)');
$pdo->exec('CREATE TABLE goals (id INTEGER PRIMARY KEY, is_active INTEGER)');
$pdo->exec('CREATE TABLE user_goals (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, goal_id INTEGER, priority INTEGER, status TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE goal_preference_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, goal_id INTEGER, pref_key TEXT, label_key TEXT, helper_text_key TEXT, input_type TEXT, value_type TEXT, allowed_values_json TEXT, weight REAL, is_required INTEGER, is_active INTEGER)');
$pdo->exec('CREATE TABLE user_goal_preferences (id INTEGER PRIMARY KEY AUTOINCREMENT, user_goal_id INTEGER, preference_def_id INTEGER, value_string TEXT, value_number REAL, value_bool INTEGER, value_json TEXT, created_at TEXT, updated_at TEXT)');

$pdo->exec("INSERT INTO goals (id, is_active) VALUES (1,1), (2,1)");
$pdo->exec("INSERT INTO goal_preference_definitions (goal_id, pref_key, label_key, helper_text_key, input_type, value_type, allowed_values_json, weight, is_required, is_active) VALUES
    (1,'travel_style','x',NULL,'select','string','[\"economy\",\"comfort\"]',1,1,1),
    (2,'relationship_pace','x',NULL,'select','string','[\"slow\",\"balanced\"]',1,1,1)");

$repo = new OnboardingRepository($pdo);

// Boundaries: same key, multiple values should be storable without unique-key crash.
$repo->replaceBoundaries(7, [
    ['key' => 'privacy', 'value' => 'no_personal_details_early', 'importance' => 'required'],
    ['key' => 'privacy', 'value' => 'no_recording_without_consent', 'importance' => 'preferred'],
]);
$boundaries = $repo->getBoundariesForUser(7);
oassert(count($boundaries) === 2, 'multiple boundary items in same category should save');
$values = array_column($boundaries, 'boundary_value');
oassert(in_array('no_personal_details_early', $values, true) && in_array('no_recording_without_consent', $values, true), 'boundary readback should preserve values');

// Goal preferences save/readback.
$repo->replaceGoals(7, [1, 2], 2);
$goalOrder = $repo->getActiveGoalIdsForUser(7);
oassert($goalOrder === [2, 1], 'primary goal should be stored with highest priority');
$stateBeforePrefs = $repo->completionState(7);
oassert(($stateBeforePrefs['goal_questions'] ?? true) === false, 'goal-questions step should be incomplete before saving required answers');
$repo->replaceGoalPreferenceValues(7, [
    1 => ['travel_style' => 'economy'],
    2 => ['relationship_pace' => ['balanced']],
]);
$prefs = $repo->getGoalPreferenceValuesForUser(7);
oassert(($prefs[1]['travel_style'] ?? '') === 'economy', 'goal preference 1 should persist');
oassert(($prefs[2]['relationship_pace'][0] ?? '') === 'balanced', 'goal preference 2 should persist (array/json path)');
$stateAfterPrefs = $repo->completionState(7);
oassert(($stateAfterPrefs['goal_questions'] ?? false) === true, 'goal-questions step should be complete after required answers');

// Updating should replace old rows, not duplicate crash.
$repo->replaceGoalPreferenceValues(7, [
    1 => ['travel_style' => 'comfort'],
]);
$prefs2 = $repo->getGoalPreferenceValuesForUser(7);
oassert(($prefs2[1]['travel_style'] ?? '') === 'comfort', 'goal preference update should replace previous value');

echo "OK: onboarding boundaries + goal preference verification passed\n";
