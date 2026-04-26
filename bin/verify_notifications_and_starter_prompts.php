#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Repositories\ChatRepository;
use App\Repositories\Matching\MatchCardRepository;
use App\Repositories\Matching\MatchInterestRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\RevealRepository;
use App\Services\ChatService;
use App\Services\GoalAwareStarterPromptService;
use App\Services\Matching\AgeLabelBuilderService;
use App\Services\Matching\DistanceBucketService;
use App\Services\Matching\EmotionalSummaryBuilderService;
use App\Services\Matching\MatchCardBuilderService;
use App\Services\Matching\MatchInterestService;
use App\Services\NotificationService;
use App\Services\RevealService;

require __DIR__ . '/../bootstrap/autoload.php';

function vassert(bool $ok, string $msg): void
{
    if (!$ok) {
        throw new RuntimeException("Assertion failed: {$msg}");
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE goals (id INTEGER PRIMARY KEY, slug TEXT NOT NULL)');
$pdo->exec('CREATE TABLE profiles (user_id INTEGER PRIMARY KEY, birth_year INTEGER, country_code TEXT, region_code TEXT, location_cell_l4 TEXT, location_cell_l5 TEXT, communication_style INTEGER, social_energy INTEGER)');
$pdo->exec('CREATE TABLE profile_boundaries (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, boundary_key TEXT, boundary_value TEXT, importance TEXT)');
$pdo->exec('CREATE TABLE matches (id INTEGER PRIMARY KEY AUTOINCREMENT, user_a_id INTEGER, user_b_id INTEGER, goal_id INTEGER, score_a_to_b REAL, score_b_to_a REAL, mutual_score REAL, explanation_json TEXT, status TEXT, created_at TEXT, updated_at TEXT, chat_opened_at TEXT, closed_at TEXT, closed_reason_code TEXT)');
$pdo->exec('CREATE TABLE chats (id INTEGER PRIMARY KEY AUTOINCREMENT, match_id INTEGER, status TEXT, opened_at TEXT)');
$pdo->exec('CREATE TABLE messages (id INTEGER PRIMARY KEY AUTOINCREMENT, chat_id INTEGER, sender_user_id INTEGER, message_body TEXT, message_type TEXT, metadata_json TEXT, moderation_state TEXT, created_at TEXT, deleted_at TEXT)');
$pdo->exec('CREATE TABLE match_interest_actions (id INTEGER PRIMARY KEY AUTOINCREMENT, match_id INTEGER, actor_user_id INTEGER, action TEXT, metadata_json TEXT, created_at TEXT)');
$pdo->exec('CREATE TABLE match_interest_states (id INTEGER PRIMARY KEY AUTOINCREMENT, match_id INTEGER, user_id INTEGER, current_interest TEXT, updated_at TEXT, UNIQUE(match_id,user_id))');
$pdo->exec('CREATE TABLE match_candidate_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, candidate_user_id INTEGER, goal_id INTEGER, hard_filter_passed INTEGER, compatibility_score REAL, score_breakdown_json TEXT, status TEXT, expires_at TEXT, processed_at TEXT)');
$pdo->exec('CREATE TABLE match_cards (id INTEGER PRIMARY KEY AUTOINCREMENT, match_id INTEGER, viewer_user_id INTEGER, age_range_label_key TEXT, approx_distance_bucket TEXT, compatibility_score REAL, emotional_summary_key TEXT, match_reasons_json TEXT, communication_boundaries_json TEXT, schedule_overlap_key TEXT, card_version INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(match_id,viewer_user_id))');
$pdo->exec('CREATE TABLE revealable_profile_data (user_id INTEGER PRIMARY KEY, first_name TEXT, photo_media_key TEXT, contact_payload_json TEXT, deep_profile_payload_json TEXT, first_name_reveal_stage TEXT, photo_reveal_stage TEXT, contact_reveal_stage TEXT, deep_profile_reveal_stage TEXT)');
$pdo->exec('CREATE TABLE reveal_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, match_id INTEGER, requested_by_user_id INTEGER, reveal_type TEXT, stage_required TEXT, requested_at TEXT, expires_at TEXT, status TEXT, resolved_at TEXT)');
$pdo->exec('CREATE TABLE reveal_consents (id INTEGER PRIMARY KEY AUTOINCREMENT, reveal_request_id INTEGER, user_id INTEGER, consent_status TEXT, consented_at TEXT, UNIQUE(reveal_request_id,user_id))');
$pdo->exec('CREATE TABLE notification_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, template_key TEXT UNIQUE, category TEXT, title_text_key TEXT, body_text_key TEXT, is_active INTEGER)');
$pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, template_id INTEGER, actor_user_id INTEGER, entity_type TEXT, entity_id INTEGER, payload_json TEXT, is_read INTEGER DEFAULT 0, send_after TEXT, delivered_at TEXT, created_at TEXT)');

$templates = [
    ['notification.strong_match_available', 'match'],
    ['notification.mutual_interest_created', 'match'],
    ['notification.reveal_request_received', 'reveal'],
    ['notification.reveal_request_accepted', 'reveal'],
    ['notification.reveal_request_declined', 'reveal'],
    ['notification.reveal_request_cancelled', 'reveal'],
    ['notification.reveal_request_expired', 'reveal'],
    ['notification.new_message_received', 'chat'],
];
$stmtTpl = $pdo->prepare('INSERT INTO notification_templates (template_key, category, title_text_key, body_text_key, is_active) VALUES (:k,:c,:t,:b,1)');
foreach ($templates as [$key, $cat]) {
    $stmtTpl->execute(['k' => $key, 'c' => $cat, 't' => $key . '.title', 'b' => $key . '.body']);
}

$pdo->exec("INSERT INTO users (id,status) VALUES (1,'active'),(2,'active'),(3,'active')");
$pdo->exec("INSERT INTO goals (id,slug) VALUES (1,'emotional_connection')");
$pdo->exec("INSERT INTO profiles (user_id,birth_year,country_code,region_code,location_cell_l4,location_cell_l5,communication_style,social_energy) VALUES (1,1992,'IR','THR','THR','THR5',3,3),(2,1990,'IR','THR','THR','THR5',3,3)");
$pdo->exec("INSERT INTO match_candidate_queue (user_id,candidate_user_id,goal_id,hard_filter_passed,compatibility_score,score_breakdown_json,status,expires_at,processed_at) VALUES (1,2,1,1,91,'{\"score_breakdown\":{\"schedule_overlap\":80},\"top_match_reasons\":[\"explanation.value_1\"]}','scored',datetime('now','+1 day'),datetime('now'))");

$notifications = new NotificationService(new NotificationRepository($pdo));
$builder = new MatchCardBuilderService(new MatchCardRepository($pdo), new AgeLabelBuilderService(), new DistanceBucketService(), new EmotionalSummaryBuilderService(), 1, $notifications);
$built = $builder->buildOrRefreshForUser(1, 10);
vassert($built === 1, 'strong match card should build');
$strongCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications n JOIN notification_templates t ON t.id=n.template_id WHERE t.template_key='notification.strong_match_available'")->fetchColumn();
vassert($strongCount === 1, 'strong_match_available should be emitted');

$matchId = (int)$pdo->query('SELECT id FROM matches LIMIT 1')->fetchColumn();
$interestService = new MatchInterestService(new MatchInterestRepository($pdo), $notifications, $pdo);
$interestService->applyAction($matchId, 1, 'interested');
$res = $interestService->applyAction($matchId, 2, 'interested');
vassert(($res['chat_created'] ?? false) === true, 'chat should open on mutual interest');
$mutualCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications n JOIN notification_templates t ON t.id=n.template_id WHERE t.template_key='notification.mutual_interest_created'")->fetchColumn();
vassert($mutualCount === 2, 'mutual notification should be emitted to both users');

$chatId = (int)$res['chat_id'];
$promptRows = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE chat_id = {$chatId} AND message_type = 'prompt'")->fetchColumn();
vassert($promptRows >= 2, 'starter prompts should be inserted once');
$interestService->applyAction($matchId, 2, 'undo');
$interestService->applyAction($matchId, 2, 'interested');
$promptRowsAfter = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE chat_id = {$chatId} AND message_type = 'prompt'")->fetchColumn();
vassert($promptRowsAfter === $promptRows, 'starter prompts should not duplicate');

$pdo->exec("INSERT INTO revealable_profile_data (user_id,first_name,photo_media_key,contact_payload_json,deep_profile_payload_json,first_name_reveal_stage,photo_reveal_stage,contact_reveal_stage,deep_profile_reveal_stage) VALUES (1,'Ali',NULL,NULL,NULL,'stage_1','stage_2','stage_3','stage_3'),(2,'Sara',NULL,NULL,NULL,'stage_1','stage_2','stage_3','stage_3')");
$revealService = new RevealService(new RevealRepository($pdo), $notifications);
$requestId = $revealService->createRequest($matchId, 1, 'first_name');
$receivedCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications n JOIN notification_templates t ON t.id=n.template_id WHERE t.template_key='notification.reveal_request_received'")->fetchColumn();
vassert($receivedCount >= 1, 'reveal_request_received should be emitted');
$revealService->respond($requestId, 2, 'accept');
$acceptedCount = (int)$pdo->query("SELECT COUNT(*) FROM notifications n JOIN notification_templates t ON t.id=n.template_id WHERE t.template_key='notification.reveal_request_accepted'")->fetchColumn();
vassert($acceptedCount >= 1, 'reveal_request_accepted should be emitted');

$recent = $notifications->recentForUser(1, 20);
vassert(count($recent) > 0, 'recent notifications should load');
$unread = $notifications->unreadCount(1);
vassert($unread > 0, 'unread count should be > 0');
$notifications->markRead(1, (int)$recent[0]['id']);
$notifications->markAllRead(1);
vassert($notifications->unreadCount(1) === 0, 'all notifications should be markable as read');

$chatService = new ChatService(new ChatRepository($pdo), new GoalAwareStarterPromptService());
$keys = $chatService->starterPromptKeysForChat($chatId);
vassert(count($keys) === 3 && str_starts_with($keys[0], 'chat.starter.goal.'), 'starter prompt keys should be goal-aware');

echo "OK: notifications + goal-aware starter prompts verification passed\n";
