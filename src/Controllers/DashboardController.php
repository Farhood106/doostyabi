<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Core\Request;
use App\Core\View;
use App\Repositories\Matching\MatchCardRepository;
use App\Services\Matching\MatchDeliveryService;
use PDO;

final class DashboardController
{
    public function __construct(private readonly \App\Core\App $app) {}

    public function index(Request $request): void
    {
        $userId = (int)($this->app->make(AuthService::class)->userId() ?? 0);
        $cards = [];
        if ($userId > 0) {
            $repo = new MatchCardRepository($this->app->make(PDO::class));
            $cards = (new MatchDeliveryService($repo))->cardsForViewer($userId, 20, 0);
        }

        View::render('dashboard', ['cards' => $cards]);
    }
}
