<?php

declare(strict_types=1);

namespace App\Services\Matching;

final class AgeLabelBuilderService
{
    public function fromBirthYear(?int $birthYear): string
    {
        if (!$birthYear || $birthYear < 1900) {
            return 'age_unknown';
        }

        $age = max(18, (int)date('Y') - $birthYear);
        if ($age <= 24) {
            return 'age_18_24';
        }
        if ($age >= 60) {
            return 'age_60_plus';
        }

        $start = (int)(floor(($age - 25) / 5) * 5 + 25);
        $end = $start + 4;

        return sprintf('age_%d_%d', $start, $end);
    }
}
