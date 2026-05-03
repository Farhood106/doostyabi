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
        if (!$this->genderCompatible($source, $candidate)) {
            $reasons[] = 'gender_interest_mismatch';
        }
        if (!$this->locationScopeCompatible($source, $candidate)) {
            $reasons[] = 'strict_location_scope_mismatch';
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

    private function genderCompatible(array $source, array $candidate): bool
    {
        $aInterest = trim((string)($source['interested_in_gender'] ?? ''));
        $bInterest = trim((string)($candidate['interested_in_gender'] ?? ''));
        $aGender = trim((string)($source['gender_identity'] ?? ''));
        $bGender = trim((string)($candidate['gender_identity'] ?? ''));
        $aOk = ($aInterest === '' || $aInterest === $bGender);
        $bOk = ($bInterest === '' || $bInterest === $aGender);
        return $aOk && $bOk;
    }

    private function locationScopeCompatible(array $source, array $candidate): bool
    {
        $primaryGoal = (int)($source['goals'][0] ?? 0);
        $prefs = (array)($source['goal_preferences'][$primaryGoal] ?? []);
        $scope = (string)($prefs['seek.location_scope'] ?? '');
        if ($scope === 'city_only') {
            return (string)($source['location_cell_l5'] ?? '') !== '' && (($source['location_cell_l5'] ?? '') === ($candidate['location_cell_l5'] ?? ''));
        }
        if ($scope === 'same_province') {
            return (string)($source['region_code'] ?? '') !== '' && (($source['region_code'] ?? '') === ($candidate['region_code'] ?? ''));
        }
        return true;
    }

    private function ageCompatible(array $a, array $b): bool
    {
        if (empty($a['birth_year']) || empty($b['birth_year'])) {
            return false;
        }
        if (!isset($a['age_min_pref'], $a['age_max_pref'], $b['age_min_pref'], $b['age_max_pref'])) {
            return false;
        }

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
            $importance = strtolower(trim((string)($r['importance'] ?? '')));
            if ($importance === 'required') {
                $aReq[$key] = true;
            }
            if ($importance === 'avoid') {
                $aAvoid[$key] = true;
            }
        }

        foreach ($bBoundaries as $r) {
            $key = $r['boundary_key'] . '|' . $r['boundary_value'];
            $importance = strtolower(trim((string)($r['importance'] ?? '')));
            if ($importance === 'required') {
                $bReq[$key] = true;
            }
            if ($importance === 'avoid') {
                $bAvoid[$key] = true;
            }
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
