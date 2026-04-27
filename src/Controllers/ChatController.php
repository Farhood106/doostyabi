<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthService;
use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\ChatRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\RevealRepository;
use App\Security\Csrf;
use App\Services\ChatService;
use App\Services\GoalAwareStarterPromptService;
use App\Services\NotificationService;
use App\Services\RevealService;
use InvalidArgumentException;
use PDO;

final class ChatController
{
    public function __construct(private readonly App $app) {}

    public function show(Request $request): void
    {
        $userId = (int)($this->app->make(AuthService::class)->userId() ?? 0);
        if ($userId <= 0) {
            Response::redirect('/login');
        }

        $chatId = $request->input('chat_id') !== null ? (int)$request->input('chat_id') : null;
        $matchId = $request->input('match_id') !== null ? (int)$request->input('match_id') : null;

        try {
            $service = new ChatService(new ChatRepository($this->app->make(PDO::class)), new GoalAwareStarterPromptService());
            $chat = $service->openContext($userId, $chatId, $matchId);
            $service->ensureGoalStarterPromptMessages((int)$chat['chat_id'], (int)$chat['user_a_id'], (int)$chat['match_id']);
            $starterPromptKeys = $service->starterPromptKeysForChat((int)$chat['chat_id']);
            $revealPanel = (new RevealService(
                new RevealRepository($this->app->make(PDO::class)),
                new NotificationService(new NotificationRepository($this->app->make(PDO::class)))
            ))
                ->panel((int)$chat['match_id'], $userId);
            View::render('chat/show', [
                'chat' => $chat,
                'revealPanel' => $revealPanel,
                'message' => flashGet('message'),
                'starterPromptKeys' => $starterPromptKeys,
                'currentUserId' => $userId,
            ]);
        } catch (InvalidArgumentException) {
            flash('message', 'chat.access_denied');
            Response::redirect('/dashboard');
        }
    }

    public function send(Request $request): never
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

        $chatId = (int)$request->input('chat_id');
        $body = (string)$request->input('message_body');

        try {
            $service = new ChatService(new ChatRepository($this->app->make(PDO::class)), new GoalAwareStarterPromptService());
            $context = $service->openContext($userId, $chatId, null);
            $service->sendMessage($userId, $chatId, $body, 'text');
            $otherUserId = (int)$context['user_a_id'] === $userId ? (int)$context['user_b_id'] : (int)$context['user_a_id'];
            (new NotificationService(new NotificationRepository($this->app->make(PDO::class))))
                ->emit('new_message_received', $otherUserId, $userId, 'chat', $chatId, ['message_preview' => mb_substr($body, 0, 80)]);
            flash('message', 'chat.message_sent');
        } catch (InvalidArgumentException $e) {
            flash('message', match ($e->getMessage()) {
                'invalid_message_body' => 'chat.invalid_message_body',
                'chat_not_found_or_forbidden', 'chat_closed', 'match_not_chat_open' => 'chat.access_denied',
                default => 'chat.send_failed',
            });
        } catch (\Throwable) {
            flash('message', 'common.unexpected_error');
        }

        Response::redirect('/chat?chat_id=' . $chatId);
    }

    public function poll(Request $request): never
    {
        $userId = (int)($this->app->make(AuthService::class)->userId() ?? 0);
        if ($userId <= 0) {
            $this->json(['error' => 'unauthorized'], 401);
        }

        $chatId = (int)$request->input('chat_id');
        $sinceId = (int)$request->input('since_id', 0);

        try {
            $service = new ChatService(new ChatRepository($this->app->make(PDO::class)));
            $messages = $service->poll($userId, $chatId, $sinceId);
            foreach ($messages as &$m) {
                $typeKey = 'chat.message_type.' . (string)($m['message_type'] ?? 'text');
                $m['message_type_label'] = t($typeKey);
            }
            $this->json(['messages' => $messages]);
        } catch (InvalidArgumentException) {
            $this->json(['error' => 'forbidden'], 403);
        }
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
