<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\RevealRepository;
use App\Security\Csrf;
use App\Services\RevealService;
use InvalidArgumentException;
use PDO;

final class RevealController
{
    public function __construct(private readonly App $app) {}

    public function create(Request $request): never
    {
        if ($this->authUserId() <= 0) {
            Response::redirect('/login');
        }

        if (!$this->csrfOk($request)) {
            flash('message', 'security.invalid_csrf');
            Response::redirect('/dashboard');
        }

        $userId = $this->authUserId();
        $matchId = (int)$request->input('match_id');
        $chatId = (int)$request->input('chat_id');
        $revealType = trim((string)$request->input('reveal_type'));

        try {
            $this->service()->createRequest($matchId, $userId, $revealType);
            flash('message', 'reveal.request_created');
        } catch (InvalidArgumentException $e) {
            flash('message', 'reveal.error.' . $e->getMessage());
        } catch (\Throwable) {
            flash('message', 'common.unexpected_error');
        }

        Response::redirect('/chat?chat_id=' . $chatId);
    }

    public function respond(Request $request): never
    {
        if ($this->authUserId() <= 0) {
            Response::redirect('/login');
        }

        if (!$this->csrfOk($request)) {
            flash('message', 'security.invalid_csrf');
            Response::redirect('/dashboard');
        }

        $userId = $this->authUserId();
        $requestId = (int)$request->input('request_id');
        $chatId = (int)$request->input('chat_id');
        $decision = trim((string)$request->input('decision'));

        try {
            $this->service()->respond($requestId, $userId, $decision);
            flash('message', $decision === 'accept' ? 'reveal.request_accepted' : 'reveal.request_declined');
        } catch (InvalidArgumentException $e) {
            flash('message', 'reveal.error.' . $e->getMessage());
        } catch (\Throwable) {
            flash('message', 'common.unexpected_error');
        }

        Response::redirect('/chat?chat_id=' . $chatId);
    }

    public function cancel(Request $request): never
    {
        if ($this->authUserId() <= 0) {
            Response::redirect('/login');
        }

        if (!$this->csrfOk($request)) {
            flash('message', 'security.invalid_csrf');
            Response::redirect('/dashboard');
        }

        $userId = $this->authUserId();
        $requestId = (int)$request->input('request_id');
        $chatId = (int)$request->input('chat_id');

        try {
            $this->service()->cancel($requestId, $userId);
            flash('message', 'reveal.request_cancelled');
        } catch (InvalidArgumentException $e) {
            flash('message', 'reveal.error.' . $e->getMessage());
        } catch (\Throwable) {
            flash('message', 'common.unexpected_error');
        }

        Response::redirect('/chat?chat_id=' . $chatId);
    }

    private function service(): RevealService
    {
        return new RevealService(new RevealRepository($this->app->make(PDO::class)));
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
