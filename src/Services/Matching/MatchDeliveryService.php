<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Repositories\Matching\MatchCardRepository;

final class MatchDeliveryService
{
    public function __construct(private readonly MatchCardRepository $repo) {}

    public function cardsForViewer(int $viewerUserId, int $limit = 20, int $offset = 0): array
    {
        return $this->repo->fetchDisplayableCards($viewerUserId, $limit, $offset);
    }
}
