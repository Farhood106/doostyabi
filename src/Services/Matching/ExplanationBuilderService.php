<?php

declare(strict_types=1);

namespace App\Services\Matching;

final class ExplanationBuilderService
{
    public function build(array $scoring): array
    {
        return [
            'summary_key' => 'match.explanation.summary',
            'top_match_reasons' => $scoring['top_match_reasons'],
            'caution_points' => $scoring['caution_points'],
            'score_breakdown' => $scoring['score_breakdown'],
            'privacy_level' => 'anonymous_stage_1',
        ];
    }
}
