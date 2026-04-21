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

        // Mandatory geography gate for MVP safety: same country only.
        // Region/cell are ranking signals, not hard reject signals.
        if (($source['country_code'] ?? '') !== ($candidate['country_code'] ?? '')) {
            $reasons[] = 'country_mismatch';
        }

        if ($this->hasBoundaryConflict($source['boundaries'], $candidate['boundaries'])) {
            $reasons[] = 'boundary_conflict';
        }

        // Hard-filter only strong lifestyle contradictions.
        if ($this->strongLifestyleConflict($source, $candidate)) {
            $reasons[] = 'lifestyle_conflict_strong';
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

    private function hasBoundaryConflict(array $aBoundaries, array $bBoundaries): bool
    {
        $aReq = $aAvoid = $bReq = $bAvoid = [];

        foreach ($aBoundaries as $r) {
            $key = $r['boundary_key'] . '|' . $r['boundary_value'];
            if ($r['importance'] === 'required') $aReq[$key] = true;
            if ($r['importance'] === 'avoid') $aAvoid[$key] = true;
        }

        foreach ($bBoundaries as $r) {
            $key = $r['boundary_key'] . '|' . $r['boundary_value'];
            if ($r['importance'] === 'required') $bReq[$key] = true;
            if ($r['importance'] === 'avoid') $bAvoid[$key] = true;
        }

        // Required vs avoid conflicts both directions
        foreach (array_keys($aReq) as $k) {
            if (isset($bAvoid[$k])) return true;
        }
        foreach (array_keys($bReq) as $k) {
            if (isset($aAvoid[$k])) return true;
        }

        // Required item missing on the other side entirely
        $bAll = [];
        foreach ($bBoundaries as $r) $bAll[$r['boundary_key'] . '|' . $r['boundary_value']] = true;
        foreach (array_keys($aReq) as $k) if (!isset($bAll[$k])) return true;

        $aAll = [];
        foreach ($aBoundaries as $r) $aAll[$r['boundary_key'] . '|' . $r['boundary_value']] = true;
        foreach (array_keys($bReq) as $k) if (!isset($aAll[$k])) return true;

        return false;
    }

    private function strongLifestyleConflict(array $a, array $b): bool
    {
        $smokeConflict = (($a['smoking_preference'] ?? '') === 'no' && ($b['smoking_preference'] ?? '') === 'yes')
            || (($b['smoking_preference'] ?? '') === 'no' && ($a['smoking_preference'] ?? '') === 'yes');

        $drinkConflict = (($a['drinking_preference'] ?? '') === 'no' && ($b['drinking_preference'] ?? '') === 'yes')
            || (($b['drinking_preference'] ?? '') === 'no' && ($a['drinking_preference'] ?? '') === 'yes');

        return $smokeConflict && $drinkConflict;
    }
}
