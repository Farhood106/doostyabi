<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Request;
use App\Repositories\Matching\CandidateRepository;
use App\Services\Matching\CandidateGenerationService;
use App\Services\Matching\CandidateQueueService;
use App\Services\Matching\CompatibilityScoringService;
use App\Services\Matching\ExplanationBuilderService;
use App\Services\Matching\HardFilterService;
use App\Services\Matching\NoMatchStateService;

require __DIR__ . '/../bootstrap/autoload.php';
require __DIR__ . '/../src/Support/helpers.php';

$config = [
    'app' => require __DIR__ . '/../config/app.php',
    'database' => require __DIR__ . '/../config/database.php',
];

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['app']['session_name']);
    session_start();
}

$request = new Request('CLI', '/cron/candidates', [], [], []);
$app = new App($config, $request);
$GLOBALS['app'] = $app;

$repo = new CandidateRepository($app->make(\PDO::class));
$generator = new CandidateGenerationService(
    $repo,
    new HardFilterService($repo),
    new CompatibilityScoringService(),
    new ExplanationBuilderService(),
    new CandidateQueueService($repo),
    new NoMatchStateService($repo),
);

$limit = (int)($argv[1] ?? 50);
$offset = (int)($argv[2] ?? 0);
$candidateLimit = (int)($argv[3] ?? 200);

$users = $repo->usersBatch($limit, $offset);

foreach ($users as $row) {
    $generator->processUser((int)$row['user_id'], $candidateLimit);
}

echo sprintf("processed_users=%d offset=%d limit=%d\n", count($users), $offset, $limit);
