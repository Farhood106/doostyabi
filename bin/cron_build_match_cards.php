<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;
use App\Repositories\Matching\MatchCardRepository;
use App\Services\Matching\AgeLabelBuilderService;
use App\Services\Matching\DistanceBucketService;
use App\Services\Matching\EmotionalSummaryBuilderService;
use App\Services\Matching\MatchCardBuilderService;
use App\Repositories\NotificationRepository;
use App\Services\NotificationService;

require __DIR__ . '/../bootstrap/autoload.php';

$config = [
    'app' => require __DIR__ . '/../config/app.php',
    'database' => require __DIR__ . '/../config/database.php',
];

$app = new App($config, new Request('CLI', '/cron/build-match-cards', [], [], []));
$GLOBALS['app'] = $app;

$repo = new MatchCardRepository($app->make(PDO::class));
$builder = new MatchCardBuilderService(
    $repo,
    new AgeLabelBuilderService(),
    new DistanceBucketService(),
    new EmotionalSummaryBuilderService(),
    1,
    new NotificationService(new NotificationRepository($app->make(PDO::class)))
);

$userLimit = (int)($argv[1] ?? 50);
$userOffset = (int)($argv[2] ?? 0);
$cardLimitPerUser = (int)($argv[3] ?? 100);

$users = $repo->usersBatch($userLimit, $userOffset);
$totalCards = 0;
foreach ($users as $row) {
    $totalCards += $builder->buildOrRefreshForUser((int)$row['user_id'], $cardLimitPerUser);
}

echo sprintf("processed_users=%d built_or_refreshed_cards=%d offset=%d limit=%d\n", count($users), $totalCards, $userOffset, $userLimit);
