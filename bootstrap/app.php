<?php

declare(strict_types=1);

use App\Auth\AuthService;
use App\Core\App;
use App\Core\Request;
use App\Core\Router;

require __DIR__ . '/autoload.php';
require __DIR__ . '/../src/Support/helpers.php';

$config = [
    'app' => require __DIR__ . '/../config/app.php',
    'database' => require __DIR__ . '/../config/database.php',
];

session_name($config['app']['session_name']);
session_start();

$request = Request::capture();
$app = new App($config, $request);
$GLOBALS['app'] = $app;

$router = new Router();

$authMiddleware = static function (Request $request, App $app): void {
    if (!$app->make(AuthService::class)->userId()) {
        App\Core\Response::redirect('/login');
    }
};

$guestMiddleware = static function (Request $request, App $app): void {
    if ($app->make(AuthService::class)->userId()) {
        App\Core\Response::redirect('/dashboard');
    }
};

require __DIR__ . '/../routes/web.php';

return [
    'app' => $app,
    'router' => $router,
    'request' => $request,
];
