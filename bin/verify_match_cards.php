<?php

declare(strict_types=1);

use App\Repositories\Matching\MatchCardRepository;
use App\Services\Matching\AgeLabelBuilderService;
use App\Services\Matching\DistanceBucketService;
use App\Services\Matching\EmotionalSummaryBuilderService;
use App\Services\Matching\MatchCardBuilderService;
use App\Services\Matching\MatchDeliveryService;

require __DIR__ . '/../bootstrap/autoload.php';

function verifyAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE goals (id INTEGER PRIMARY KEY, slug TEXT, title_key TEXT)');
$pdo->exec('CREATE TABLE profiles (
    user_id INTEGER PRIMARY KEY,
    birth_year INTEGER,
    country_code TEXT,
    region_code TEXT,
    location_cell_l4 TEXT,
    location_cell_l5 TEXT,
    communication_style INTEGER,
    social_energy INTEGER,
    boundary_sensitivity INTEGER
)');
$pdo->exec('CREATE TABLE profile_boundaries (user_id INTEGER, boundary_key TEXT, boundary_value TEXT, importance TEXT)');
$pdo->exec('CREATE TABLE match_candidate_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    candidate_user_id INTEGER,
    goal_id INTEGER,
    hard_filter_passed INTEGER,
    compatibility_score REAL,
    score_breakdown_json TEXT,
    status TEXT,
    expires_at TEXT,
    processed_at TEXT
)');
$pdo->exec('CREATE TABLE matches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_a_id INTEGER,
    user_b_id INTEGER,
    goal_id INTEGER,
    score_a_to_b REAL,
    score_b_to_a REAL,
    mutual_score REAL,
    explanation_json TEXT,
    status TEXT,
    created_at TEXT,
    updated_at TEXT,
    UNIQUE (user_a_id, user_b_id, goal_id)
)');
$pdo->exec('CREATE TABLE match_interest_states (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    match_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    current_interest TEXT NOT NULL,
    updated_at TEXT,
    UNIQUE(match_id, user_id)
)');
$pdo->exec('CREATE TABLE chats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    match_id INTEGER NOT NULL,
    status TEXT,
    opened_at TEXT
)');
$pdo->exec('CREATE TABLE match_cards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    match_id INTEGER,
    viewer_user_id INTEGER,
    age_range_label_key TEXT,
    approx_distance_bucket TEXT,
    compatibility_score REAL,
    emotional_summary_key TEXT,
    match_reasons_json TEXT,
    communication_boundaries_json TEXT,
    schedule_overlap_key TEXT,
    card_version INTEGER,
    created_at TEXT,
    updated_at TEXT,
    UNIQUE(match_id, viewer_user_id)
)');

$pdo->exec("INSERT INTO users (id, status) VALUES (1,'active'), (2,'active'), (3,'active')");
$pdo->exec("INSERT INTO goals (id, slug, title_key) VALUES (1,'friendly_conversation','goals.friendly_conversation.title')");
$pdo->exec("INSERT INTO profiles (user_id, birth_year, country_code, region_code, location_cell_l4, location_cell_l5, communication_style, social_energy, boundary_sensitivity) VALUES
(1, 1993, 'US', 'CA', 'L4-1', 'L5-1', 3, 3, 4),
(2, 1996, 'US', 'CA', 'L4-1', 'L5-2', 3, 3, 4),
(3, 1991, 'US', 'CA', 'L4-2', 'L5-7', 4, 2, 3)");

$pdo->exec("INSERT INTO profile_boundaries (user_id, boundary_key, boundary_value, importance) VALUES
(2, 'communication_tone', 'kind', 'required'),
(2, 'name', 'private', 'required'),
(3, 'response_window', '24h', 'avoid')");

$breakdownA = json_encode([
    'top_match_reasons' => ['explanation.shared_goal_intention', 'unsafe.non_key_should_be_removed'],
    'score_breakdown' => ['schedule_overlap' => 72, 'distance_fit' => 75, 'dimensions' => 70],
]);
$breakdownB = json_encode([
    'top_match_reasons' => ['explanation.schedule_overlap_good'],
    'score_breakdown' => ['schedule_overlap' => 45, 'distance_fit' => 55, 'dimensions' => 60],
]);

$now = date('Y-m-d H:i:s');
$future = date('Y-m-d H:i:s', strtotime('+1 day'));
$pdo->prepare('INSERT INTO match_candidate_queue (user_id, candidate_user_id, goal_id, hard_filter_passed, compatibility_score, score_breakdown_json, status, expires_at, processed_at) VALUES (1,2,1,1,88,:a,\'scored\',:f,:n), (1,3,1,1,66,:b,\'scored\',:f,:n)')
    ->execute(['a' => $breakdownA, 'b' => $breakdownB, 'f' => $future, 'n' => $now]);

$repo = new MatchCardRepository($pdo);
$builder = new MatchCardBuilderService(
    $repo,
    new AgeLabelBuilderService(),
    new DistanceBucketService(),
    new EmotionalSummaryBuilderService(),
    1
);
$built = $builder->buildOrRefreshForUser(1, 20);
verifyAssert($built === 2, 'should build two cards');

$delivery = new MatchDeliveryService($repo);
$cards = $delivery->cardsForViewer(1, 10, 0);
verifyAssert(count($cards) === 2, 'delivery should return two cards');
verifyAssert((float)$cards[0]['compatibility_score'] >= (float)$cards[1]['compatibility_score'], 'cards should be ordered by score desc');

$topCard = $cards[0];
verifyAssert(isset($topCard['age_range_label_key']) && str_starts_with((string)$topCard['age_range_label_key'], 'age_'), 'age label key should be generated');
verifyAssert(in_array($topCard['approx_distance_bucket'], ['same_area', 'nearby_region', 'same_country'], true), 'distance bucket should be privacy-safe');
verifyAssert(in_array($topCard['schedule_overlap_key'], ['schedule_overlap_high', 'schedule_overlap_medium', 'schedule_overlap_low'], true), 'schedule overlap key should be categorized');
verifyAssert(!in_array('unsafe.non_key_should_be_removed', $topCard['match_reasons_json'], true), 'unsafe reason should be stripped');

$boundariesJson = json_encode($topCard['communication_boundaries_json']);
verifyAssert($boundariesJson !== false && strpos($boundariesJson, 'name') === false, 'identity boundary keys should be stripped from card payload');

$presentedCount = (int)$pdo->query("SELECT COUNT(*) FROM match_candidate_queue WHERE user_id = 1 AND status = 'presented'")->fetchColumn();
verifyAssert($presentedCount === 2, 'queue rows should be marked presented after card build');

echo "OK: match card build and delivery verification passed\n";
