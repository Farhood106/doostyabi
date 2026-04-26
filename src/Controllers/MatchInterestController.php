<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Matching\MatchInterestRepository;
use App\Repositories\NotificationRepository;
use App\Security\Csrf;
use App\Services\NotificationService;
use App\Services\Matching\MatchInterestService;
use InvalidArgumentException;
use PDO;

final class MatchInterestController
{
    public function __construct(private readonly App $app) {}

    public function store(Request $request): never
    {
        $csrf = $this->app->make(Csrf::class);
        if (!$csrf->validate((string)$request->input('_token'))) {
            flash('message', 'security.invalid_csrf');
            Response::redirect('/dashboard');
        }

        $userId = (int)($this->app->make(AuthService::class)->userId() ?? 0);
        if ($userId <= 0) {
            Response::redirect('/login');
        }

        $matchId = (int)$request->input('match_id');
        $action = trim((string)$request->input('action'));

        try {
            $repo = new MatchInterestRepository($this->app->make(PDO::class));
            $service = new MatchInterestService(
                $repo,
                new NotificationService(new NotificationRepository($this->app->make(PDO::class))),
                $this->app->make(PDO::class)
            );
            $result = $service->applyAction($matchId, $userId, $action);

            if (($result['match_status'] ?? '') === 'chat_open') {
                flash('message', 'match.interest.chat_opened');
            } else {
                flash('message', 'match.interest.action_saved');
            }
        } catch (InvalidArgumentException $e) {
            $messageKey = match ($e->getMessage()) {
                'invalid_action' => 'match.interest.invalid_action',
                'match_not_found' => 'match.interest.match_not_found',
                'forbidden_actor' => 'match.interest.forbidden_actor',
                default => 'match.interest.invalid_action',
            };
            flash('message', $messageKey);
        } catch (\Throwable) {
            flash('message', 'common.unexpected_error');
        }

        Response::redirect('/dashboard');
    }
}
