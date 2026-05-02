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
use App\Services\OnboardingProgressService;
use PDO;

final class DashboardController
{
    public function __construct(private readonly \App\Core\App $app) {}

    public function index(Request $request): void
    {
        $userId = (int)($this->app->make(AuthService::class)->userId() ?? 0);
        if ($userId > 0) {
            $firstIncomplete = $this->app->make(OnboardingProgressService::class)->firstIncompleteStep($userId);
            if ($firstIncomplete !== 'done') {
                flash('message', 'برای فعال شدن معرفی‌ها، لطفاً پرسش‌های تکمیلی هدف اصلی را کامل کنید.');
                header('Location: /onboarding/' . $firstIncomplete);
                exit;
            }
        }
        $cards = [];
        $passedCards = [];
        $notifications = [];
        $unreadNotifications = 0;
        $message = flashGet('message');
        if ($userId > 0) {
            $repo = new MatchCardRepository($this->app->make(PDO::class));
            $cards = (new MatchDeliveryService($repo))->cardsForViewer($userId, 20, 0);
            $passedCards = $repo->fetchPassedCards($userId, 20);
            $notificationService = new NotificationService(new NotificationRepository($this->app->make(PDO::class)));
            $notifications = array_map(fn(array $n): array => $this->withAction($n), $notificationService->recentForUser($userId, 10));
            $unreadNotifications = $notificationService->unreadCount($userId);
        }

        View::render('dashboard', [
            'cards' => $cards,
            'passedCards' => $passedCards,
            'message' => $message,
            'notifications' => $notifications,
            'unreadNotifications' => $unreadNotifications,
        ]);
    }

    private function withAction(array $notification): array
    {
        $templateKey = (string)($notification['template_key'] ?? '');
        $entityType = (string)($notification['entity_type'] ?? '');
        $entityId = isset($notification['entity_id']) ? (int)$notification['entity_id'] : null;
        $payload = is_array($notification['payload'] ?? null) ? $notification['payload'] : [];

        $notification['action_url'] = null;
        $notification['action_label_key'] = null;

        if ($templateKey === 'notification.strong_match_available') {
            $counterpartId = isset($payload['counterpart_user_id']) ? (int)$payload['counterpart_user_id'] : null;
            if ($counterpartId === null && $entityType === 'counterpart' && $entityId !== null) {
                $counterpartId = $entityId;
            }
            $notification['action_url'] = $counterpartId !== null && $counterpartId > 0
                ? '/dashboard#match-card-' . $counterpartId
                : '/dashboard#matches';
            $notification['action_label_key'] = 'dashboard.notifications.action.view_match';
            return $notification;
        }

        if ($templateKey === 'notification.mutual_interest_created' && isset($payload['chat_id'])) {
            $notification['action_url'] = '/chat?chat_id=' . (int)$payload['chat_id'];
            $notification['action_label_key'] = 'dashboard.notifications.action.open_chat';
            return $notification;
        }

        if (in_array($templateKey, ['notification.reveal_request_received', 'notification.reveal_request_accepted', 'notification.reveal_request_declined', 'notification.reveal_request_cancelled', 'notification.reveal_request_expired'], true) && isset($payload['match_id'])) {
            $notification['action_url'] = isset($payload['chat_id'])
                ? '/chat?chat_id=' . (int)$payload['chat_id']
                : '/chat?match_id=' . (int)$payload['match_id'];
            $notification['action_label_key'] = 'dashboard.notifications.action.open_reveal';
            return $notification;
        }

        if ($templateKey === 'notification.new_message_received' && $entityType === 'chat' && $entityId !== null) {
            $notification['action_url'] = '/chat?chat_id=' . $entityId;
            $notification['action_label_key'] = 'dashboard.notifications.action.open_chat';
            return $notification;
        }

        if (in_array($templateKey, ['notification.reveal_request_received', 'notification.reveal_request_accepted', 'notification.reveal_request_declined', 'notification.reveal_request_cancelled', 'notification.reveal_request_expired'], true)) {
            $notification['action_url'] = '/dashboard#matches';
            $notification['action_label_key'] = 'dashboard.notifications.action.open_reveal';
        }

        return $notification;
    }
}
