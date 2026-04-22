<?php

declare(strict_types=1);

use App\Repositories\Matching\MatchCardRepository;
use App\Repositories\Matching\MatchInterestRepository;
use App\Services\Matching\MatchDeliveryService;
use App\Services\Matching\MatchInterestService;

require __DIR__ . '/../bootstrap/autoload.php';

function assertMutual(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE matches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_a_id INTEGER NOT NULL,
    user_b_id INTEGER NOT NULL,
    goal_id INTEGER NOT NULL,
    score_a_to_b REAL NOT NULL,
    score_b_to_a REAL NOT NULL,
    mutual_score REAL NOT NULL,
    explanation_json TEXT NOT NULL,
    status TEXT NOT NULL,
    created_at TEXT,
    updated_at TEXT
)');
$pdo->exec('CREATE TABLE match_interest_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    match_id INTEGER NOT NULL,
    actor_user_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    metadata_json TEXT,
    created_at TEXT
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
    status TEXT NOT NULL,
    opened_at TEXT,
    UNIQUE(match_id)
)');
$pdo->exec('CREATE TABLE match_cards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    match_id INTEGER NOT NULL,
    viewer_user_id INTEGER NOT NULL,
    age_range_label_key TEXT,
    approx_distance_bucket TEXT,
    compatibility_score REAL,
    emotional_summary_key TEXT,
    match_reasons_json TEXT,
    communication_boundaries_json TEXT,
    schedule_overlap_key TEXT,
    card_version INTEGER,
    created_at TEXT,
    updated_at TEXT
)');

$pdo->exec("INSERT INTO users (id, status) VALUES (1,'active'),(2,'active')");
$pdo->exec("INSERT INTO matches (id, user_a_id, user_b_id, goal_id, score_a_to_b, score_b_to_a, mutual_score, explanation_json, status, created_at, updated_at)
            VALUES (10,1,2,1,80,78,79,'{}','suggested',datetime('now'),datetime('now'))");
$pdo->exec("INSERT INTO match_cards (match_id, viewer_user_id, age_range_label_key, approx_distance_bucket, compatibility_score, emotional_summary_key, match_reasons_json, communication_boundaries_json, schedule_overlap_key, card_version, created_at, updated_at)
            VALUES
            (10,1,'age_25_29','same_area',88,'calm_and_respectful_connection','[]','[]','schedule_overlap_high',1,datetime('now'),datetime('now')),
            (10,2,'age_25_29','same_area',88,'calm_and_respectful_connection','[]','[]','schedule_overlap_high',1,datetime('now'),datetime('now'))");

$interestRepo = new MatchInterestRepository($pdo);
$service = new MatchInterestService($interestRepo);

// interested action stored
$service->applyAction(10, 1, 'interested');
$actionCountInterested = (int)$pdo->query("SELECT COUNT(*) FROM match_interest_actions WHERE match_id = 10 AND actor_user_id = 1 AND action = 'interested'")->fetchColumn();
assertMutual($actionCountInterested === 1, 'interested action should be stored');

// pass action stored
$service->applyAction(10, 1, 'pass');
$actionCountPass = (int)$pdo->query("SELECT COUNT(*) FROM match_interest_actions WHERE match_id = 10 AND actor_user_id = 1 AND action = 'pass'")->fetchColumn();
assertMutual($actionCountPass === 1, 'pass action should be stored');

// passed card excluded from normal delivery
$delivery = new MatchDeliveryService(new MatchCardRepository($pdo));
$cardsAfterPass = $delivery->cardsForViewer(1, 20, 0);
assertMutual(count($cardsAfterPass) === 0, 'passed card should be excluded from active delivery');

// undo action stored and state reset to none
$service->applyAction(10, 1, 'undo');
$actionCountUndo = (int)$pdo->query("SELECT COUNT(*) FROM match_interest_actions WHERE match_id = 10 AND actor_user_id = 1 AND action = 'undo'")->fetchColumn();
assertMutual($actionCountUndo === 1, 'undo action should be stored');
$stateAfterUndo = (string)$pdo->query("SELECT current_interest FROM match_interest_states WHERE match_id = 10 AND user_id = 1")->fetchColumn();
assertMutual($stateAfterUndo === 'none', 'undo should reset interest state to none');

// mutual detection + chat creation
$service->applyAction(10, 1, 'interested');
$resultMutual = $service->applyAction(10, 2, 'interested');
assertMutual(($resultMutual['match_status'] ?? '') === 'chat_open', 'mutual interest should move match to chat_open lifecycle state');
$chatCount = (int)$pdo->query('SELECT COUNT(*) FROM chats WHERE match_id = 10')->fetchColumn();
assertMutual($chatCount === 1, 'chat should be created once mutual interest is confirmed');

echo "OK: mutual-interest verification passed\n";
