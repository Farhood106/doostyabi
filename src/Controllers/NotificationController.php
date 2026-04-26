<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\NotificationRepository;
use App\Security\Csrf;
use App\Services\NotificationService;
use PDO;

final class NotificationController
{
    public function __construct(private readonly App $app) {}

    public function markRead(Request $request): never
    {
        if (!$this->csrfOk($request)) {
            flash('message', 'security.invalid_csrf');
            Response::redirect('/dashboard');
        }

        $userId = $this->authUserId();
        if ($userId <= 0) {
            Response::redirect('/login');
        }

        $notificationId = (int)$request->input('notification_id');
        $this->service()->markRead($userId, $notificationId);
        Response::redirect('/dashboard');
    }

    public function markAllRead(Request $request): never
    {
        if (!$this->csrfOk($request)) {
            flash('message', 'security.invalid_csrf');
            Response::redirect('/dashboard');
        }

        $userId = $this->authUserId();
        if ($userId <= 0) {
            Response::redirect('/login');
        }

        $this->service()->markAllRead($userId);
        Response::redirect('/dashboard');
    }

    private function service(): NotificationService
    {
        return new NotificationService(new NotificationRepository($this->app->make(PDO::class)));
    }

    private function authUserId(): int
    {
        return (int)($this->app->make(AuthService::class)->userId() ?? 0);
    }

    private function csrfOk(Request $request): bool
    {
        return $this->app->make(Csrf::class)->validate((string)$request->input('_token'));
    }
}
