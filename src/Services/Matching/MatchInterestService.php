<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Repositories\Matching\MatchInterestRepository;
use App\Repositories\ChatRepository;
use App\Services\ChatService;
use App\Services\GoalAwareStarterPromptService;
use App\Services\NotificationService;
use InvalidArgumentException;
use PDO;

final class MatchInterestService
{
    public function __construct(
        private readonly MatchInterestRepository $repo,
        private readonly ?NotificationService $notifications = null,
        private readonly ?PDO $pdo = null,
    ) {}

    public function applyAction(int $matchId, int $actorUserId, string $action): array
    {
        if (!in_array($action, ['interested', 'pass', 'undo'], true)) {
            throw new InvalidArgumentException('invalid_action');
        }

        $participants = $this->repo->matchParticipants($matchId);
        if (!$participants) {
            throw new InvalidArgumentException('match_not_found');
        }

        $a = (int)$participants['user_a_id'];
        $b = (int)$participants['user_b_id'];
        if ($actorUserId !== $a && $actorUserId !== $b) {
            throw new InvalidArgumentException('forbidden_actor');
        }

        $currentInterest = match ($action) {
            'interested' => 'interested',
            'pass' => 'passed',
            'undo' => 'none',
        };

        $canonicalMatchId = $this->repo->canonicalMatchIdForPair($a, $b) ?? $matchId;
        $participants = $this->repo->matchParticipants($canonicalMatchId) ?? $participants;
        $a = (int)$participants['user_a_id'];
        $b = (int)$participants['user_b_id'];
        $alreadyChatId = $this->repo->chatByMatchId($canonicalMatchId);
        $alreadyOpen = ((string)$participants['status'] === 'chat_open') && $alreadyChatId !== null;

        $this->repo->begin();
        try {
            $this->repo->appendAction($canonicalMatchId, $actorUserId, $action);
            $this->repo->upsertInterestState($canonicalMatchId, $actorUserId, $currentInterest);

            $stateMap = [];
            foreach ($this->repo->interestStatesByMatch($canonicalMatchId) as $row) {
                $stateMap[(int)$row['user_id']] = (string)$row['current_interest'];
            }

            $aState = $stateMap[$a] ?? 'none';
            $bState = $stateMap[$b] ?? 'none';

            $result = [
                'match_id' => $canonicalMatchId,
                'actor_interest' => $currentInterest,
                'match_status' => (string)$participants['status'],
                'chat_created' => false,
                'chat_id' => null,
            ];

            if ($aState === 'interested' && $bState === 'interested') {
                $this->repo->updateMatchStatus($canonicalMatchId, 'mutual');
                $chatId = $this->repo->ensureChatExists($canonicalMatchId);
                $this->repo->updateMatchStatus($canonicalMatchId, 'chat_open');
                $this->repo->syncPairStatusToCanonical($canonicalMatchId, $a, $b, 'chat_open');

                $result['match_status'] = 'chat_open';
                $result['chat_created'] = !$alreadyOpen;
                $result['chat_id'] = $chatId;
            } elseif ($aState === 'passed' || $bState === 'passed') {
                // Keep match in suggested but pass state hides cards from the passer in delivery.
                $this->repo->updateMatchStatus($canonicalMatchId, 'suggested');
                $result['match_status'] = 'suggested';
            } elseif (($aState === 'interested' && $bState === 'none') || ($bState === 'interested' && $aState === 'none')) {
                $this->repo->updateMatchStatus($canonicalMatchId, 'interested_one_side');
                $result['match_status'] = 'interested_one_side';
            } elseif ($aState === 'none' || $bState === 'none') {
                $this->repo->updateMatchStatus($canonicalMatchId, 'suggested');
                $result['match_status'] = 'suggested';
            }

            $this->repo->commit();
            if (($result['chat_created'] ?? false) === true) {
                $this->notifications?->emit('mutual_interest_created', $a, $actorUserId, 'match', $canonicalMatchId, ['chat_id' => $result['chat_id']]);
                $this->notifications?->emit('mutual_interest_created', $b, $actorUserId, 'match', $canonicalMatchId, ['chat_id' => $result['chat_id']]);

                if ($this->pdo !== null && !empty($result['chat_id'])) {
                    $chatService = new ChatService(new ChatRepository($this->pdo), new GoalAwareStarterPromptService());
                    $chatService->ensureGoalStarterPromptMessages((int)$result['chat_id'], $a, $canonicalMatchId);
                }
            }

            return $result;
        } catch (\Throwable $e) {
            $this->repo->rollback();
            throw $e;
        }
    }
}
