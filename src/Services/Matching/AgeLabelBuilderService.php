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
        $start = (int)(floor(($age - 20) / 5) * 5 + 20);
        if ($start < 18) {
            $start = 18;
        }

        $end = $start + 4;

        return sprintf('age_%d_%d', $start, $end);
    }
}
