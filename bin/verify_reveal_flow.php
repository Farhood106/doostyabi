<?php

declare(strict_types=1);

use App\Repositories\RevealRepository;
use App\Services\RevealService;

require __DIR__ . '/../bootstrap/autoload.php';

function assertReveal(bool $ok, string $msg): void
{
    if (!$ok) throw new RuntimeException("Assertion failed: {$msg}");
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE matches (id INTEGER PRIMARY KEY, user_a_id INTEGER, user_b_id INTEGER, status TEXT)');
$pdo->exec('CREATE TABLE revealable_profile_data (
 user_id INTEGER PRIMARY KEY,
 first_name TEXT,
 photo_media_key TEXT,
 contact_payload_json TEXT,
 deep_profile_payload_json TEXT,
 first_name_reveal_stage TEXT,
 photo_reveal_stage TEXT,
 contact_reveal_stage TEXT,
 deep_profile_reveal_stage TEXT
)');
$pdo->exec('CREATE TABLE reveal_requests (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 match_id INTEGER,
 requested_by_user_id INTEGER,
 reveal_type TEXT,
 stage_required TEXT,
 requested_at TEXT,
 expires_at TEXT,
 status TEXT,
 resolved_at TEXT
)');
$pdo->exec('CREATE TABLE reveal_consents (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 reveal_request_id INTEGER,
 user_id INTEGER,
 consent_status TEXT,
 consented_at TEXT,
 UNIQUE(reveal_request_id, user_id)
)');

$pdo->exec("INSERT INTO users (id, status) VALUES (1,'active'),(2,'active'),(3,'active')");
$pdo->exec("INSERT INTO matches (id, user_a_id, user_b_id, status) VALUES (10,1,2,'chat_open')");
$pdo->exec("INSERT INTO revealable_profile_data (user_id, first_name, photo_media_key, contact_payload_json, deep_profile_payload_json, first_name_reveal_stage, photo_reveal_stage, contact_reveal_stage, deep_profile_reveal_stage)
            VALUES (1,'Sara','p1.jpg','{\"phone\":\"123\"}','{\"bio\":\"deep\"}','stage_2','stage_2','stage_3','stage_3')");

$service = new RevealService(new RevealRepository($pdo));

// request creation
$requestId = $service->createRequest(10, 1, 'first_name');
assertReveal($requestId > 0, 'request should be created');

// duplicate pending should be blocked
$duplicateBlocked = false;
try {
    $service->createRequest(10, 1, 'first_name');
} catch (InvalidArgumentException $e) {
    $duplicateBlocked = $e->getMessage() === 'duplicate_pending_request';
}
assertReveal($duplicateBlocked, 'duplicate pending request should be blocked');

// hidden until unlocked
$panelBefore = $service->panel(10, 2);
assertReveal(count($panelBefore['unlocked']) === 0, 'revealed data should be hidden before two-sided consent');

// accept flow => visible after proper consent
$service->respond($requestId, 2, 'accept');
$panelAfter = $service->panel(10, 2);
assertReveal(count($panelAfter['unlocked']) === 1, 'revealed data should be visible after accept flow');

// decline flow
$requestDecline = $service->createRequest(10, 1, 'photo');
$service->respond($requestDecline, 2, 'decline');
$declined = (string)$pdo->query("SELECT status FROM reveal_requests WHERE id = {$requestDecline}")->fetchColumn();
assertReveal($declined === 'declined', 'decline flow should set declined status');

// expired pending request should not accept/decline/cancel
$requestExpired = $service->createRequest(10, 1, 'contact_info');
$pdo->exec("UPDATE reveal_requests SET expires_at = '2000-01-01 00:00:00' WHERE id = {$requestExpired}");
$expiredRespondBlocked = false;
try {
    $service->respond($requestExpired, 2, 'accept');
} catch (InvalidArgumentException $e) {
    $expiredRespondBlocked = $e->getMessage() === 'request_not_pending';
}
assertReveal($expiredRespondBlocked, 'expired request should not be respondable');

$requestExpired2 = $service->createRequest(10, 1, 'contact_info');
$pdo->exec("UPDATE reveal_requests SET expires_at = '2000-01-01 00:00:00' WHERE id = {$requestExpired2}");
$expiredCancelBlocked = false;
try {
    $service->cancel($requestExpired2, 1);
} catch (InvalidArgumentException $e) {
    $expiredCancelBlocked = $e->getMessage() === 'request_not_pending';
}
assertReveal($expiredCancelBlocked, 'expired request should not be cancellable');

// unauthorized access blocked
$blocked = false;
try {
    $service->createRequest(10, 3, 'first_name');
} catch (InvalidArgumentException) {
    $blocked = true;
}
assertReveal($blocked, 'unauthorized user should be blocked');

echo "OK: reveal flow verification passed\n";
