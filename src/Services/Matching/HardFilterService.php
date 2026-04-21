<?php

declare(strict_types=1);

namespace App\Services\Matching;

use App\Repositories\Matching\CandidateRepository;

final class HardFilterService
{
    public function __construct(private readonly CandidateRepository $repo) {}

    public function evaluate(array $source, array $candidate): array
    {
        $reasons = [];

        if ((int)$source['user_id'] === (int)$candidate['user_id']) {
            $reasons[] = 'self_excluded';
        }

        if (($source['status'] ?? '') !== 'active' || ($candidate['status'] ?? '') !== 'active') {
            $reasons[] = 'inactive_user';
        }

        if ($this->repo->isBlocked((int)$source['user_id'], (int)$candidate['user_id'])) {
            $reasons[] = 'blocked_pair';
        }

        $goalOverlap = array_values(array_intersect($source['goals'], $candidate['goals']));
        if ($goalOverlap === []) {
            $reasons[] = 'goal_mismatch';
        }

        if (!$this->ageCompatible($source, $candidate)) {
            $reasons[] = 'age_preference_mismatch';
        }

        if (!$this->locationCompatible($source, $candidate)) {
            $reasons[] = 'distance_too_far';
        }

        if ($this->hasBoundaryConflict($source['boundaries'], $candidate['boundaries'])) {
            $reasons[] = 'boundary_conflict';
        }

        if ($this->lifestyleConflict($source, $candidate)) {
            $reasons[] = 'lifestyle_conflict';
        }

        return [
            'eligible_for_display' => $reasons === [],
            'rejection_reason_codes' => $reasons,
            'goal_overlap' => $goalOverlap,
        ];
    }

    private function ageCompatible(array $a, array $b): bool
    {
        $year = (int)date('Y');
        $ageA = $year - (int)$a['birth_year'];
        $ageB = $year - (int)$b['birth_year'];

        return $ageB >= (int)$a['age_min_pref'] && $ageB <= (int)$a['age_max_pref']
            && $ageA >= (int)$b['age_min_pref'] && $ageA <= (int)$b['age_max_pref'];
    }

    private function locationCompatible(array $a, array $b): bool
    {
        if (($a['country_code'] ?? '') !== ($b['country_code'] ?? '')) return false;
        if (($a['region_code'] ?? '') !== ($b['region_code'] ?? '')) return false;

        return (($a['location_cell_l5'] ?? '') === ($b['location_cell_l5'] ?? ''))
            || (($a['location_cell_l4'] ?? '') === ($b['location_cell_l4'] ?? ''));
    }

    private function hasBoundaryConflict(array $aBoundaries, array $bBoundaries): bool
    {
        $index = [];
        foreach ($bBoundaries as $b) {
            $index[$b['boundary_key'] . '|' . $b['boundary_value']] = $b['importance'];
        }

        foreach ($aBoundaries as $a) {
            $key = $a['boundary_key'] . '|' . $a['boundary_value'];
            if (!isset($index[$key])) {
                if ($a['importance'] === 'required') {
                    return true;
                }
                continue;
            }

            if ($a['importance'] === 'avoid' && $index[$key] === 'required') {
                return true;
            }
        }

        return false;
    }

    private function lifestyleConflict(array $a, array $b): bool
    {
        $smokeConflict = (($a['smoking_preference'] ?? '') === 'no' && ($b['smoking_preference'] ?? '') === 'yes')
            || (($b['smoking_preference'] ?? '') === 'no' && ($a['smoking_preference'] ?? '') === 'yes');

        $drinkConflict = (($a['drinking_preference'] ?? '') === 'no' && ($b['drinking_preference'] ?? '') === 'yes')
            || (($b['drinking_preference'] ?? '') === 'no' && ($a['drinking_preference'] ?? '') === 'yes');

        return $smokeConflict || $drinkConflict;
    }
}
