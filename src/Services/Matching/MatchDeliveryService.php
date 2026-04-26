<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Repositories\Matching\MatchCardRepository;

final class MatchDeliveryService
{
    public function __construct(private readonly MatchCardRepository $repo) {}

    public function cardsForViewer(int $viewerUserId, int $limit = 20, int $offset = 0): array
    {
        $rows = $this->repo->fetchDisplayableCards($viewerUserId, max($limit * 3, 60), $offset);
        $unique = [];
        foreach ($rows as $row) {
            $counterpart = (int)($row['counterpart_user_id'] ?? 0);
            if ($counterpart <= 0 || $counterpart === $viewerUserId) {
                continue;
            }
            if (!isset($unique[$counterpart])) {
                $unique[$counterpart] = $row;
            }
            if (count($unique) >= $limit) {
                break;
            }
        }

        return array_values($unique);
    }
}
