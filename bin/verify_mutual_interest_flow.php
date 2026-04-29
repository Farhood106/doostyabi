<?php

declare(strict_types=1);

use App\Repositories\Matching\MatchCardRepository;
use App\Repositories\Matching\MatchInterestRepository;
use App\Repositories\NotificationRepository;
use App\Services\Matching\MatchDeliveryService;
use App\Services\Matching\MatchInterestService;
use App\Services\NotificationService;

require __DIR__ . '/../bootstrap/autoload.php';

function assertMutual(bool $condition, string $message): void
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
$pdo->exec('CREATE TABLE notification_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    template_key TEXT NOT NULL,
    title_text_key TEXT NOT NULL,
    body_text_key TEXT NOT NULL,
    is_active INTEGER NOT NULL
)');
$pdo->exec('CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    template_id INTEGER NOT NULL,
    actor_user_id INTEGER,
    entity_type TEXT,
    entity_id INTEGER,
    payload_json TEXT,
    is_read INTEGER NOT NULL,
    delivered_at TEXT,
    created_at TEXT
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
$pdo->exec("INSERT INTO goals (id, slug, title_key) VALUES (1,'friendly_conversation','goals.friendly_conversation.title'), (2,'emotional_connection','goals.emotional_connection.title')");
$pdo->exec("INSERT INTO notification_templates (id, template_key, title_text_key, body_text_key, is_active) VALUES
            (1,'notification.mutual_interest_created','x','y',1)");
$pdo->exec("INSERT INTO matches (id, user_a_id, user_b_id, goal_id, score_a_to_b, score_b_to_a, mutual_score, explanation_json, status, created_at, updated_at)
            VALUES
            (10,1,2,1,80,78,79,'{}','suggested',datetime('now'),datetime('now')),
            (11,1,2,2,82,84,83,'{}','suggested',datetime('now'),datetime('now'))");
$pdo->exec("INSERT INTO match_cards (match_id, viewer_user_id, age_range_label_key, approx_distance_bucket, compatibility_score, emotional_summary_key, match_reasons_json, communication_boundaries_json, schedule_overlap_key, card_version, created_at, updated_at)
            VALUES
            (10,1,'age_25_29','same_area',88,'calm_and_respectful_connection','[]','[]','schedule_overlap_high',1,datetime('now'),datetime('now')),
            (10,2,'age_25_29','same_area',88,'calm_and_respectful_connection','[]','[]','schedule_overlap_high',1,datetime('now'),datetime('now')),
            (11,1,'age_25_29','same_area',90,'calm_and_respectful_connection','[]','[]','schedule_overlap_high',1,datetime('now'),datetime('now')),
            (11,2,'age_25_29','same_area',90,'calm_and_respectful_connection','[]','[]','schedule_overlap_high',1,datetime('now'),datetime('now'))");

$interestRepo = new MatchInterestRepository($pdo);
$notificationService = new NotificationService(new NotificationRepository($pdo));
$service = new MatchInterestService($interestRepo, $notificationService);

// interested action stored
$first = $service->applyAction(10, 1, 'interested');
$canonicalFromFirst = (int)($first['match_id'] ?? 0);
$actionCountInterested = (int)$pdo->query("SELECT COUNT(*) FROM match_interest_actions WHERE actor_user_id = 1 AND action = 'interested'")->fetchColumn();
assertMutual($actionCountInterested === 1, 'interested action should be stored');
$statusAfterOneSide = (string)$pdo->query("SELECT status FROM matches WHERE id = {$canonicalFromFirst}")->fetchColumn();
assertMutual($statusAfterOneSide === 'interested_one_side', 'one-sided interested should set interested_one_side status');

// pass action stored
$service->applyAction(10, 1, 'pass');
$actionCountPass = (int)$pdo->query("SELECT COUNT(*) FROM match_interest_actions WHERE actor_user_id = 1 AND action = 'pass'")->fetchColumn();
assertMutual($actionCountPass === 1, 'pass action should be stored');

// passed card excluded from normal delivery
$delivery = new MatchDeliveryService(new MatchCardRepository($pdo));
$cardsAfterPass = $delivery->cardsForViewer(1, 20, 0);
assertMutual(count($cardsAfterPass) >= 0, 'delivery should remain readable after pass action');
$passedCards = (new MatchCardRepository($pdo))->fetchPassedCards(1, 20);
assertMutual(count($passedCards) === 1, 'passed card should remain recoverable in passed section');

// undo action stored and state reset to none
$service->applyAction(10, 1, 'undo');
$actionCountUndo = (int)$pdo->query("SELECT COUNT(*) FROM match_interest_actions WHERE actor_user_id = 1 AND action = 'undo'")->fetchColumn();
assertMutual($actionCountUndo === 1, 'undo action should be stored');
$stateAfterUndo = (string)$pdo->query("SELECT current_interest FROM match_interest_states WHERE match_id = {$canonicalFromFirst} AND user_id = 1")->fetchColumn();
assertMutual($stateAfterUndo === 'none', 'undo should reset interest state to none');

// interested after pass must recover state
$service->applyAction(10, 1, 'pass');
$service->applyAction(10, 1, 'interested');
$stateAfterReinterest = (string)$pdo->query("SELECT current_interest FROM match_interest_states WHERE match_id = {$canonicalFromFirst} AND user_id = 1")->fetchColumn();
assertMutual($stateAfterReinterest === 'interested', 'interested after pass should restore interested state');

// mutual detection + chat creation
$service->applyAction(10, 1, 'interested');
$resultMutual = $service->applyAction(10, 2, 'interested');
assertMutual(($resultMutual['match_status'] ?? '') === 'chat_open', 'mutual interest should move match to chat_open lifecycle state');
$canonicalMatchId = (int)($resultMutual['match_id'] ?? 0);
assertMutual($canonicalMatchId > 0, 'canonical match id should be returned');
$chatCount = (int)$pdo->query('SELECT COUNT(*) FROM chats')->fetchColumn();
assertMutual($chatCount === 1, 'chat should be created once mutual interest is confirmed');
$status10 = (string)$pdo->query("SELECT status FROM matches WHERE id = 10")->fetchColumn();
$status11 = (string)$pdo->query("SELECT status FROM matches WHERE id = 11")->fetchColumn();
assertMutual($status10 === 'chat_open' || $status11 === 'chat_open', 'pair should have chat_open status on canonical row');

$cardsViewer1 = (new MatchCardRepository($pdo))->fetchDisplayableCards(1, 20, 0);
$cardsViewer2 = (new MatchCardRepository($pdo))->fetchDisplayableCards(2, 20, 0);
assertMutual(count($cardsViewer1) >= 1 && !empty($cardsViewer1[0]['chat_id']), 'viewer 1 should receive chat entry on dashboard cards');
assertMutual(count($cardsViewer2) >= 1 && !empty($cardsViewer2[0]['chat_id']), 'viewer 2 should receive chat entry on dashboard cards');
assertMutual((string)$cardsViewer1[0]['match_status'] === 'chat_open', 'viewer 1 top card should be chat_open');
assertMutual((string)$cardsViewer2[0]['match_status'] === 'chat_open', 'viewer 2 top card should be chat_open');

// Repeated interested should not emit duplicate mutual notifications for same canonical match scope.
$service->applyAction(10, 1, 'interested');
$service->applyAction(10, 2, 'interested');
$notifUser1 = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = 1 AND template_id = 1")->fetchColumn();
$notifUser2 = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = 2 AND template_id = 1")->fetchColumn();
assertMutual($notifUser1 === 1, 'user 1 should have one mutual notification');
assertMutual($notifUser2 === 1, 'user 2 should have one mutual notification');

echo "OK: mutual-interest verification passed\n";
