<?php

declare(strict_types=1);

use App\Repositories\Matching\CandidateRepository;
use App\Services\Matching\CandidateGenerationService;
use App\Services\Matching\CandidateQueueService;
use App\Services\Matching\CompatibilityScoringService;
use App\Services\Matching\ExplanationBuilderService;
use App\Services\Matching\HardFilterService;
use App\Services\Matching\NoMatchStateService;

require __DIR__ . '/../bootstrap/autoload.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

function createRepo(): array
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE profiles (
        user_id INTEGER PRIMARY KEY,
        country_code TEXT,
        region_code TEXT,
        location_cell_l5 TEXT,
        location_cell_l4 TEXT,
        birth_year INTEGER,
        age_min_pref INTEGER,
        age_max_pref INTEGER,
        smoking_preference TEXT,
        drinking_preference TEXT,
        activity_level TEXT,
        social_energy INTEGER,
        communication_style INTEGER,
        emotional_openness INTEGER,
        relationship_pace INTEGER,
        independence_level INTEGER,
        boundary_sensitivity INTEGER,
        structure_vs_spontaneity INTEGER,
        interested_in_gender TEXT,
        gender_identity TEXT,
        about_me TEXT,
        looking_for TEXT
    )');
    $pdo->exec('CREATE TABLE user_goals (user_id INTEGER, goal_id INTEGER, status TEXT)');
    $pdo->exec('CREATE TABLE profile_boundaries (user_id INTEGER, boundary_key TEXT, boundary_value TEXT, importance TEXT)');
    $pdo->exec('CREATE TABLE availability_slots (user_id INTEGER, weekday INTEGER, start_minute INTEGER, end_minute INTEGER)');
    $pdo->exec('CREATE TABLE blocks (blocker_user_id INTEGER, blocked_user_id INTEGER)');
    $pdo->exec('CREATE TABLE match_candidate_queue (
        user_id INTEGER,
        candidate_user_id INTEGER,
        goal_id INTEGER,
        hard_filter_passed INTEGER,
        compatibility_score REAL,
        score_breakdown_json TEXT,
        rejection_reason_code TEXT,
        status TEXT,
        queued_at TEXT,
        processed_at TEXT,
        expires_at TEXT,
        UNIQUE(user_id, candidate_user_id, goal_id)
    )');
    $pdo->exec('CREATE TABLE no_match_states (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        goal_id INTEGER,
        goal_scope_key INTEGER NOT NULL DEFAULT 0,
        state TEXT,
        context_json TEXT,
        is_active INTEGER,
        next_recheck_at TEXT,
        notify_on_strong_match INTEGER,
        created_at TEXT,
        updated_at TEXT
    )');

    return [$pdo, new CandidateRepository($pdo)];
}

function seedUser(PDO $pdo, int $id, array $profile): void
{
    $pdo->prepare('INSERT INTO users (id, status) VALUES (?, ?)')->execute([$id, $profile['status'] ?? 'active']);
    $pdo->prepare(
        'INSERT INTO profiles (user_id, country_code, region_code, location_cell_l5, location_cell_l4, birth_year, age_min_pref, age_max_pref, smoking_preference, drinking_preference, activity_level, social_energy, communication_style, emotional_openness, relationship_pace, independence_level, boundary_sensitivity, structure_vs_spontaneity, interested_in_gender, gender_identity, about_me, looking_for)
         VALUES (:user_id, :country_code, :region_code, :location_cell_l5, :location_cell_l4, :birth_year, :age_min_pref, :age_max_pref, :smoking_preference, :drinking_preference, :activity_level, :social_energy, :communication_style, :emotional_openness, :relationship_pace, :independence_level, :boundary_sensitivity, :structure_vs_spontaneity, :interested_in_gender, :gender_identity, :about_me, :looking_for)'
    )->execute([
        'user_id' => $id,
        'country_code' => $profile['country_code'] ?? 'US',
        'region_code' => $profile['region_code'] ?? 'CA',
        'location_cell_l5' => $profile['location_cell_l5'] ?? 'L5-A',
        'location_cell_l4' => $profile['location_cell_l4'] ?? 'L4-A',
        'birth_year' => $profile['birth_year'] ?? 1995,
        'age_min_pref' => $profile['age_min_pref'] ?? 18,
        'age_max_pref' => $profile['age_max_pref'] ?? 80,
        'smoking_preference' => $profile['smoking_preference'] ?? 'no',
        'drinking_preference' => $profile['drinking_preference'] ?? 'no',
        'activity_level' => $profile['activity_level'] ?? 'medium',
        'social_energy' => $profile['social_energy'] ?? 3,
        'communication_style' => $profile['communication_style'] ?? 3,
        'emotional_openness' => $profile['emotional_openness'] ?? 3,
        'relationship_pace' => $profile['relationship_pace'] ?? 3,
        'independence_level' => $profile['independence_level'] ?? 3,
        'boundary_sensitivity' => $profile['boundary_sensitivity'] ?? 3,
        'structure_vs_spontaneity' => $profile['structure_vs_spontaneity'] ?? 3,
        'interested_in_gender' => $profile['interested_in_gender'] ?? null,
        'gender_identity' => $profile['gender_identity'] ?? 'woman',
        'about_me' => array_key_exists('about_me', $profile) ? $profile['about_me'] : 'about',
        'looking_for' => array_key_exists('looking_for', $profile) ? $profile['looking_for'] : 'looking',
    ]);

    foreach (($profile['goals'] ?? [1]) as $goalId) {
        $pdo->prepare('INSERT INTO user_goals (user_id, goal_id, status) VALUES (?, ?, ?)')->execute([$id, $goalId, 'active']);
    }
    foreach (($profile['availability'] ?? [[1, 600, 660]]) as $slot) {
        $pdo->prepare('INSERT INTO availability_slots (user_id, weekday, start_minute, end_minute) VALUES (?, ?, ?, ?)')->execute([$id, $slot[0], $slot[1], $slot[2]]);
    }
    foreach (($profile['boundaries'] ?? []) as $boundary) {
        $pdo->prepare('INSERT INTO profile_boundaries (user_id, boundary_key, boundary_value, importance) VALUES (?, ?, ?, ?)')
            ->execute([$id, $boundary[0], $boundary[1], $boundary[2]]);
    }
}

function testSelectionBucketsAndQueue(): void
{
    [$pdo, $repo] = createRepo();
    seedUser($pdo, 1, ['goals' => [1, 2], 'location_cell_l5' => 'L5-A', 'location_cell_l4' => 'L4-A']);
    seedUser($pdo, 2, ['goals' => [1, 2], 'location_cell_l5' => 'L5-A', 'location_cell_l4' => 'L4-A']); // strict
    seedUser($pdo, 3, ['goals' => [1], 'location_cell_l5' => 'L5-B', 'location_cell_l4' => 'L4-A']); // relaxed
    seedUser($pdo, 4, ['goals' => [2], 'region_code' => 'TX', 'location_cell_l5' => 'L5-Z', 'location_cell_l4' => 'L4-Z']); // fallback

    $generator = new CandidateGenerationService(
        $repo,
        new HardFilterService($repo),
        new CompatibilityScoringService(),
        new ExplanationBuilderService(),
        new CandidateQueueService($repo),
        new NoMatchStateService($repo),
    );

    $generator->processUser(1, 3);

    $queuedIds = $pdo->query('SELECT candidate_user_id FROM match_candidate_queue WHERE user_id = 1 GROUP BY candidate_user_id ORDER BY candidate_user_id')
        ->fetchAll(PDO::FETCH_COLUMN);
    $queuedIds = array_map(static fn($v): int => (int)$v, $queuedIds);
    assertTrue($queuedIds === [2, 3, 4], 'strict/relaxed/fallback candidates should all be queued in order');

    $goalRows = (int)$pdo->query('SELECT COUNT(*) FROM match_candidate_queue WHERE user_id = 1 AND candidate_user_id = 2')->fetchColumn();
    assertTrue($goalRows === 2, 'queue rows must be written for all overlapping goals');
}

function testHardRejections(): void
{
    [$pdo, $repo] = createRepo();
    seedUser($pdo, 1, ['goals' => [1]]);
    seedUser($pdo, 2, ['goals' => [1]]);
    seedUser($pdo, 3, ['goals' => [1], 'country_code' => 'CA']);
    $pdo->prepare('INSERT INTO blocks (blocker_user_id, blocked_user_id) VALUES (?, ?)')->execute([1, 2]);

    $source = $repo->userContext(1);
    $blocked = $repo->userContext(2);
    $otherCountry = $repo->userContext(3);

    $hardFilter = new HardFilterService($repo);
    $blockedResult = $hardFilter->evaluate($source, $blocked);
    $countryResult = $hardFilter->evaluate($source, $otherCountry);

    assertTrue(in_array('blocked_pair', $blockedResult['rejection_reason_codes'], true), 'blocked pair must be hard-rejected');
    assertTrue(in_array('country_mismatch', $countryResult['rejection_reason_codes'], true), 'country mismatch must be hard-rejected');
}

function testSymmetricScheduleOverlap(): void
{
    $service = new CompatibilityScoringService();

    $commonA = [
        'country_code' => 'US', 'region_code' => 'CA', 'location_cell_l5' => 'A', 'location_cell_l4' => 'A4',
        'smoking_preference' => 'no', 'drinking_preference' => 'no', 'activity_level' => 'medium',
        'social_energy' => 3, 'communication_style' => 3, 'emotional_openness' => 3, 'relationship_pace' => 3,
        'independence_level' => 3, 'boundary_sensitivity' => 3, 'structure_vs_spontaneity' => 3,
        'interested_in_gender' => null, 'gender_identity' => 'woman',
    ];
    $commonB = $commonA;

    $a = $commonA + ['availability' => [[ 'weekday' => 1, 'start_minute' => 600, 'end_minute' => 720 ]]];
    $b = $commonB + ['availability' => [[ 'weekday' => 1, 'start_minute' => 660, 'end_minute' => 780 ]]];

    $scoreAB = $service->score($a, $b, [1]);
    $scoreBA = $service->score($b, $a, [1]);

    $ab = $scoreAB['score_breakdown']['schedule_overlap'];
    $ba = $scoreBA['score_breakdown']['schedule_overlap'];
    assertTrue(abs($ab - $ba) < 0.00001, 'schedule overlap must be symmetric');
}

function testNoMatchLifecycle(): void
{
    // Strong candidates should deactivate no-match states.
    [$pdo, $repo] = createRepo();
    seedUser($pdo, 1, ['goals' => [1], 'about_me' => 'x', 'looking_for' => 'y']);
    seedUser($pdo, 2, ['goals' => [1], 'about_me' => 'x', 'looking_for' => 'y']);

    $pdo->prepare('INSERT INTO no_match_states (user_id, goal_id, goal_scope_key, state, context_json, is_active, next_recheck_at, notify_on_strong_match, created_at, updated_at) VALUES (1, 1, 1, ?, ?, 1, ?, 1, ?, ?)')
        ->execute(['expand_preferences_suggested', '{}', date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);

    $generator = new CandidateGenerationService(
        $repo,
        new HardFilterService($repo),
        new CompatibilityScoringService(),
        new ExplanationBuilderService(),
        new CandidateQueueService($repo),
        new NoMatchStateService($repo),
    );
    $generator->processUser(1, 5);

    $activeCount = (int)$pdo->query('SELECT COUNT(*) FROM no_match_states WHERE user_id = 1 AND goal_scope_key = 1 AND is_active = 1')->fetchColumn();
    assertTrue($activeCount === 0, 'active no-match state should be deactivated when strong candidates exist');

    // Weak profile + no strong candidates should create profile_improvement_suggested.
    [$pdo2, $repo2] = createRepo();
    seedUser($pdo2, 10, [
        'goals' => [1],
        'about_me' => '',
        'looking_for' => '',
        'social_energy' => null,
        'communication_style' => null,
        'emotional_openness' => null,
        'relationship_pace' => null,
        'independence_level' => 3,
        'boundary_sensitivity' => 3,
        'structure_vs_spontaneity' => 3,
    ]);
    seedUser($pdo2, 11, [
        'goals' => [1],
        'age_min_pref' => 99,
        'age_max_pref' => 120,
    ]);

    $generator2 = new CandidateGenerationService(
        $repo2,
        new HardFilterService($repo2),
        new CompatibilityScoringService(),
        new ExplanationBuilderService(),
        new CandidateQueueService($repo2),
        new NoMatchStateService($repo2),
    );
    $generator2->processUser(10, 5);

    $state = $pdo2->query('SELECT state FROM no_match_states WHERE user_id = 10 AND is_active = 1 ORDER BY id DESC LIMIT 1')->fetchColumn();
    assertTrue($state === 'profile_improvement_suggested', 'weak profile with no strong candidates should suggest profile improvement');
}

try {
    testSelectionBucketsAndQueue();
    testHardRejections();
    testSymmetricScheduleOverlap();
    testNoMatchLifecycle();
    echo "OK: matching phase-1 verification passed\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
