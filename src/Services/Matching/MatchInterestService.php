<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Repositories\Matching\MatchInterestRepository;
use InvalidArgumentException;

final class MatchInterestService
{
    public function __construct(private readonly MatchInterestRepository $repo) {}

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

        $this->repo->begin();
        try {
            $this->repo->appendAction($matchId, $actorUserId, $action);
            $this->repo->upsertInterestState($matchId, $actorUserId, $currentInterest);

            $stateMap = [];
            foreach ($this->repo->interestStatesByMatch($matchId) as $row) {
                $stateMap[(int)$row['user_id']] = (string)$row['current_interest'];
            }

            $aState = $stateMap[$a] ?? 'none';
            $bState = $stateMap[$b] ?? 'none';

            $result = [
                'match_id' => $matchId,
                'actor_interest' => $currentInterest,
                'match_status' => (string)$participants['status'],
                'chat_created' => false,
                'chat_id' => null,
            ];

            if ($aState === 'interested' && $bState === 'interested') {
                // Chosen lifecycle: suggested -> mutual -> chat_open (immediately when mutual confirmed)
                $this->repo->updateMatchStatus($matchId, 'mutual');
                $chatId = $this->repo->ensureChatExists($matchId);
                $this->repo->updateMatchStatus($matchId, 'chat_open');

                $result['match_status'] = 'chat_open';
                $result['chat_created'] = true;
                $result['chat_id'] = $chatId;
            } elseif ($aState === 'passed' || $bState === 'passed') {
                // Keep match in suggested but pass state hides cards from the passer in delivery.
                $this->repo->updateMatchStatus($matchId, 'suggested');
                $result['match_status'] = 'suggested';
            } elseif ($aState === 'none' || $bState === 'none') {
                $this->repo->updateMatchStatus($matchId, 'suggested');
                $result['match_status'] = 'suggested';
            }

            $this->repo->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->repo->rollback();
            throw $e;
        }
    }
}
