<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Core\Request;
use App\Core\View;
use App\Repositories\Matching\MatchCardRepository;
use App\Repositories\NotificationRepository;
use App\Services\Matching\MatchDeliveryService;
use App\Services\NotificationService;
use PDO;

final class DashboardController
{
    public function __construct(private readonly \App\Core\App $app) {}

    public function index(Request $request): void
    {
        $userId = (int)($this->app->make(AuthService::class)->userId() ?? 0);
        $cards = [];
        $notifications = [];
        $unreadNotifications = 0;
        $message = flashGet('message');
        if ($userId > 0) {
            $repo = new MatchCardRepository($this->app->make(PDO::class));
            $cards = (new MatchDeliveryService($repo))->cardsForViewer($userId, 20, 0);
            $notificationService = new NotificationService(new NotificationRepository($this->app->make(PDO::class)));
            $notifications = $notificationService->recentForUser($userId, 10);
            $unreadNotifications = $notificationService->unreadCount($userId);
        }

        View::render('dashboard', [
            'cards' => $cards,
            'message' => $message,
            'notifications' => $notifications,
            'unreadNotifications' => $unreadNotifications,
        ]);
    }
}
