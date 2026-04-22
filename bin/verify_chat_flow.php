<?php

declare(strict_types=1);

use App\Repositories\ChatRepository;
use App\Services\ChatService;

require __DIR__ . '/../bootstrap/autoload.php';

function assertChat(bool $ok, string $msg): void
{
    if (!$ok) {
        throw new RuntimeException("Assertion failed: {$msg}");
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
$pdo->exec('CREATE TABLE chats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    match_id INTEGER NOT NULL,
    status TEXT NOT NULL,
    opened_at TEXT
)');
$pdo->exec('CREATE TABLE messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chat_id INTEGER NOT NULL,
    sender_user_id INTEGER NOT NULL,
    message_body TEXT NOT NULL,
    message_type TEXT NOT NULL,
    metadata_json TEXT,
    moderation_state TEXT,
    created_at TEXT,
    deleted_at TEXT
)');

$pdo->exec("INSERT INTO users (id, status) VALUES (1,'active'),(2,'active'),(3,'active')");
$pdo->exec("INSERT INTO matches (id, user_a_id, user_b_id, goal_id, score_a_to_b, score_b_to_a, mutual_score, explanation_json, status, created_at, updated_at)
            VALUES (10,1,2,1,80,80,80,'{}','chat_open',datetime('now'),datetime('now'))");
$pdo->exec("INSERT INTO chats (id, match_id, status, opened_at) VALUES (100,10,'open',datetime('now'))");
$pdo->exec("INSERT INTO messages (chat_id, sender_user_id, message_body, message_type, moderation_state, created_at) VALUES
            (100,1,'hello one','text','clean',datetime('now')),
            (100,2,'hello two','prompt','clean',datetime('now'))");

$service = new ChatService(new ChatRepository($pdo));

// authorized chat access
$ctx = $service->openContext(1, 100, null);
assertChat((int)$ctx['chat_id'] === 100, 'authorized participant should access chat');

// unauthorized chat access blocked
$blocked = false;
try {
    $service->openContext(3, 100, null);
} catch (InvalidArgumentException) {
    $blocked = true;
}
assertChat($blocked, 'non participant access should be blocked');

// send message success
$newId = $service->sendMessage(1, 100, 'new message from user1', 'text');
assertChat($newId > 0, 'participant should send message successfully');

// send blocked for non-participant
$blockedSend = false;
try {
    $service->sendMessage(3, 100, 'hijack', 'text');
} catch (InvalidArgumentException) {
    $blockedSend = true;
}
assertChat($blockedSend, 'non participant send should be blocked');

// polling returns only relevant/new messages
$pdo->exec("INSERT INTO matches (id, user_a_id, user_b_id, goal_id, score_a_to_b, score_b_to_a, mutual_score, explanation_json, status, created_at, updated_at)
            VALUES (11,1,3,1,70,70,70,'{}','chat_open',datetime('now'),datetime('now'))");
$pdo->exec("INSERT INTO chats (id, match_id, status, opened_at) VALUES (101,11,'open',datetime('now'))");
$pdo->exec("INSERT INTO messages (chat_id, sender_user_id, message_body, message_type, moderation_state, created_at) VALUES (101,1,'other chat','system','clean',datetime('now'))");

$polled = $service->poll(1, 100, 1);
assertChat(count($polled) >= 1, 'poll should return messages after since_id for the same chat');
foreach ($polled as $m) {
    assertChat((int)$m['chat_id'] === 100, 'poll should never include messages from other chats');
    assertChat((int)$m['id'] > 1, 'poll should return only new messages');
}

echo "OK: chat flow verification passed\n";
