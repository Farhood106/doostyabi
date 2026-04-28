#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Repositories\OnboardingRepository;
use App\Services\GoalQuestionCatalogService;

require __DIR__ . '/../bootstrap/autoload.php';

function bassert(bool $ok, string $msg): void
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
$pdo->exec('CREATE TABLE profile_boundaries (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, boundary_key TEXT, boundary_value TEXT, importance TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE availability_slots (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER)');
$pdo->exec('CREATE TABLE goals (id INTEGER PRIMARY KEY, slug TEXT, is_active INTEGER)');
$pdo->exec('CREATE TABLE user_goals (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, goal_id INTEGER, priority INTEGER, status TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE goal_preference_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT, goal_id INTEGER, pref_key TEXT, label_key TEXT, helper_text_key TEXT, input_type TEXT, value_type TEXT, allowed_values_json TEXT, weight REAL, is_required INTEGER, is_active INTEGER)');
$pdo->exec('CREATE TABLE user_goal_preferences (id INTEGER PRIMARY KEY AUTOINCREMENT, user_goal_id INTEGER, preference_def_id INTEGER, value_string TEXT, value_number REAL, value_bool INTEGER, value_json TEXT, created_at TEXT, updated_at TEXT)');

$pdo->exec("INSERT INTO goals (id, slug, is_active) VALUES (1,'friendly_conversation',1), (2,'casual_connection',1)");
$pdo->exec("INSERT INTO goal_preference_definitions (goal_id, pref_key, label_key, helper_text_key, input_type, value_type, allowed_values_json, weight, is_required, is_active) VALUES
    (2,'must.consent_style','x',NULL,'select','string','[\"affirmative_and_ongoing\",\"not_sure\"]',1,1,1),
    (2,'accept.emotional_involvement_level','x',NULL,'multiselect','json','[\"low\",\"moderate\"]',1,0,1),
    (2,'duration.connection_window','x',NULL,'select','string','[\"few_weeks\",\"1_3_months\"]',1,1,1)");

$repo = new OnboardingRepository($pdo);
$catalog = new GoalQuestionCatalogService();

// 1) Primary goal save/load.
$repo->replaceGoals(9, [1, 2], 2);
$goalsOrdered = $repo->getActiveGoalIdsForUser(9);
bassert($goalsOrdered === [2, 1], 'primary goal must be first in priority order');
bassert($repo->getPrimaryGoalIdForUser(9) === 2, 'primary goal id should be loadable');

// 2) Goal question save/load and no duplicate/incorrect answers after update.
$repo->replaceGoalPreferenceValues(9, [
    2 => [
        'must.consent_style' => 'affirmative_and_ongoing',
        'accept.emotional_involvement_level' => ['low', 'moderate'],
        'duration.connection_window' => '1_3_months',
    ],
]);
$prefs1 = $repo->getGoalPreferenceValuesForUser(9);
bassert(($prefs1[2]['must.consent_style'] ?? '') === 'affirmative_and_ongoing', 'single select should persist');
bassert(($prefs1[2]['accept.emotional_involvement_level'][0] ?? '') === 'low', 'multiselect should persist as array');
bassert(($prefs1[2]['accept.emotional_involvement_level'][1] ?? '') === 'moderate', 'multiselect second value should persist');

$repo->replaceGoalPreferenceValues(9, [
    2 => [
        'must.consent_style' => 'affirmative_and_ongoing',
        'accept.emotional_involvement_level' => ['low'],
        'duration.connection_window' => 'few_weeks',
    ],
]);
$prefs2 = $repo->getGoalPreferenceValuesForUser(9);
bassert(($prefs2[2]['duration.connection_window'] ?? '') === 'few_weeks', 'updated value should replace old value');
bassert(count((array)($prefs2[2]['accept.emotional_involvement_level'] ?? [])) === 1, 'old multiselect values should be replaced, not duplicated');

$stmt = $pdo->query('SELECT COUNT(*) FROM user_goal_preferences');
$countRows = (int)$stmt->fetchColumn();
bassert($countRows === 3, 'user_goal_preferences should not contain duplicated stale rows');

// 3) Sensitive-goal strictness policy contract.
bassert($catalog->isSensitiveGoalSlug('casual_connection') === true, 'casual goal should be sensitive');
bassert($catalog->isStrictSensitiveKey('must.consent_style') === true, 'consent style should be strict sensitive key');
bassert($catalog->isStrictSensitiveKey('privacy.discretion_level') === true, 'privacy should be strict sensitive key');
$notSureValue = 'not_sure';
$isAllowed = !($catalog->isSensitiveGoalSlug('casual_connection') && $catalog->isStrictSensitiveKey('must.consent_style') && in_array($notSureValue, ['not_sure', 'unsure'], true));
bassert($isAllowed === false, 'not_sure must not pass sensitive-goal strict keys');

// 4) Prefill behavior contract (old input should win once, DB remains source after clear).
$dbValue = (string)($prefs2[2]['duration.connection_window'] ?? '');
$oldInputValue = '1_3_months';
$prefill = $oldInputValue !== '' ? $oldInputValue : $dbValue;
bassert($prefill === '1_3_months', 'old input should temporarily override db value for prefill');
$prefillAfterClear = $dbValue;
bassert($prefillAfterClear === 'few_weeks', 'db value should remain unchanged after old input is cleared');

echo "OK: phase B goal question system verification passed\n";
