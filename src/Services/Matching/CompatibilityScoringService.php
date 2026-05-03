<?php

declare(strict_types=1);

namespace App\Services\Matching;

final class CompatibilityScoringService
{
    public function score(array $source, array $candidate, array $goalOverlap): array
    {
        $primaryGoalId = (int)($source['goals'][0] ?? 0);
        $primaryInOverlap = $primaryGoalId > 0 && in_array($primaryGoalId, array_map('intval', $goalOverlap), true);
        $secondaryOverlapCount = max(0, count($goalOverlap) - ($primaryInOverlap ? 1 : 0));
        $goalFit = $primaryInOverlap ? min(100.0, 75.0 + ($secondaryOverlapCount * 10.0)) : 35.0;

        $needOffer = $this->needOfferFit($source, $candidate, $goalOverlap, $primaryGoalId);
        $desiredPersonFit = $this->desiredPersonFit($source, $candidate, $primaryGoalId);
        $expectationFit = $this->namespaceFit($source, $candidate, $goalOverlap, 'privacy.', 'pace.', 'duration.', 'accept.');
        $boundarySafetyFit = $this->boundaryConsentFit($source, $candidate, $goalOverlap);
        $locationFit = $this->distanceFit($source, $candidate, $primaryGoalId, $source, $candidate);
        $availabilityFit = $this->scheduleOverlapSymmetric($source['availability'], $candidate['availability']);
        $profileContextFit = (($this->dimensionSimilarity($source, $candidate) * 0.8) + ($this->lifestyleSimilarity($source, $candidate) * 0.2));
        $confidenceScore = $this->confidenceScore($source, $primaryGoalId);
        $penalties = $needOffer['penalties'];

        $weights = [
            'goal_fit' => 22,'desired_person_fit' => 14,'need_offer_fit' => 20,'expectation_fit' => 14,'location_fit' => 12,'availability_fit' => 8,'boundary_safety_fit' => 6,'profile_context_fit' => 4,
        ];
        $weighted =
            ($goalFit * $weights['goal_fit']) +
            ($desiredPersonFit * $weights['desired_person_fit']) +
            ($needOffer['score'] * $weights['need_offer_fit']) +
            ($expectationFit * $weights['expectation_fit']) +
            ($locationFit * $weights['location_fit']) +
            ($availabilityFit * $weights['availability_fit']) +
            ($boundarySafetyFit * $weights['boundary_safety_fit']) +
            ($profileContextFit * $weights['profile_context_fit']);
        $score = round(max(0.0, (($weighted / 100.0) - $penalties) * ($confidenceScore / 100.0)), 2);

        $breakdown = [
            'goal_fit' => round($goalFit, 2),
            'desired_person_fit' => round($desiredPersonFit, 2),
            'need_offer_fit' => round((float)$needOffer['score'], 2),
            'expectation_fit' => round($expectationFit, 2),
            'location_fit' => round($locationFit, 2),
            'availability_fit' => round($availabilityFit, 2),
            'boundary_safety_fit' => round($boundarySafetyFit, 2),
            'profile_context_fit' => round($profileContextFit, 2),
            'confidence_score' => round($confidenceScore, 2),
            'penalties' => round($penalties, 2),
            'preference_matches' => $needOffer['matches'],
            'preference_gaps' => $needOffer['gaps'],
            'weights' => $weights,
        ];

        $reasons = [];
        if ($goalFit >= 65) $reasons[] = 'explanation.shared_purpose';
        if ($needOffer['score'] >= 65) $reasons[] = 'explanation.need_offer_alignment';
        if ($desiredPersonFit >= 60) $reasons[] = 'explanation.desired_person_alignment';
        if ($locationFit >= 65) $reasons[] = 'explanation.location_scope_aligned';
        if ($availabilityFit >= 60) $reasons[] = 'explanation.schedule_overlap_good';
        if ($boundarySafetyFit >= 65) $reasons[] = 'explanation.boundary_alignment';

        $cautions = [];
        if ($expectationFit < 50 || $boundarySafetyFit < 50) $cautions[] = 'explanation.caution_expectation_gap';
        if ($confidenceScore < 65) $cautions[] = 'explanation.caution_complete_goal_questions';

        return [
            'compatibility_score' => $score,
            'score_breakdown' => $breakdown,
            'top_match_reasons' => $reasons,
            'caution_points' => $cautions,
        ];
    }

    private function needOfferFit(array $source, array $candidate, array $goalOverlap, int $primaryGoalId): array
    {
        $goalIds = $primaryGoalId > 0 ? array_unique(array_merge([$primaryGoalId], $goalOverlap)) : $goalOverlap;
        $scores = [];
        $matches = [];
        $gaps = [];
        foreach ($goalIds as $goalId) {
            $s = (array)($source['goal_preferences'][(int)$goalId] ?? []);
            $c = (array)($candidate['goal_preferences'][(int)$goalId] ?? []);
            [$ab, $m1, $g1] = $this->directionalFit($s, $c, 'seek.', 'offer.');
            [$ba, $m2, $g2] = $this->directionalFit($c, $s, 'seek.', 'offer.');
            $scores[] = ($ab + $ba) / 2.0;
            $matches = array_merge($matches, $m1, $m2);
            $gaps = array_merge($gaps, $g1, $g2);
        }
        return ['score' => $scores ? array_sum($scores) / count($scores) : 50.0, 'matches' => array_values(array_unique($matches)), 'gaps' => array_values(array_unique($gaps)), 'penalties' => count(array_filter($gaps, static fn(string $g): bool => str_contains($g, ':missing_required'))) * 4.0];
    }

    private function directionalFit(array $seekPrefs, array $offerPrefs, string $seekPrefix, string $offerPrefix): array
    {
        $scores = []; $matches = []; $gaps = [];
        foreach ($seekPrefs as $key => $seekValue) {
            if (!str_starts_with((string)$key, $seekPrefix)) continue;
            $offerKey = $offerPrefix . substr((string)$key, strlen($seekPrefix));
            $offerValue = (string)($offerPrefs[$offerKey] ?? '');
            $seekValue = (string)$seekValue;
            if ($seekValue === '' || $offerValue === '') { $scores[] = 50.0; $gaps[] = $key . ':missing_required'; continue; }
            if ($seekValue === $offerValue) { $scores[] = 100.0; $matches[] = $key . ':exact'; continue; }
            $seekSet = array_values(array_filter(array_map('trim', explode(',', $seekValue))));
            $offerSet = array_values(array_filter(array_map('trim', explode(',', $offerValue))));
            $intersect = array_intersect($seekSet, $offerSet);
            if ($intersect !== []) { $scores[] = 75.0; $matches[] = $key . ':overlap'; continue; }
            $scores[] = 20.0; $gaps[] = $key . ':mismatch';
        }
        return [$scores ? array_sum($scores) / count($scores) : 50.0, $matches, $gaps];
    }

    private function namespaceFit(array $source, array $candidate, array $goalOverlap, string ...$prefixes): float
    {
        $scores = [];
        foreach ($goalOverlap as $goalId) {
            $a = (array)($source['goal_preferences'][(int)$goalId] ?? []);
            $b = (array)($candidate['goal_preferences'][(int)$goalId] ?? []);
            foreach ($a as $key => $aVal) {
                $in = false; foreach ($prefixes as $p) { if (str_starts_with((string)$key, $p)) $in = true; }
                if (!$in) continue;
                $bVal = (string)($b[$key] ?? '');
                if ((string)$aVal === '' || $bVal === '') { $scores[] = 50.0; continue; }
                $scores[] = ((string)$aVal === $bVal) ? 100.0 : ($this->isNearbyPreference((string)$aVal, $bVal) ? 65.0 : 30.0);
            }
        }
        return $scores ? array_sum($scores) / count($scores) : 50.0;
    }

    private function boundaryConsentFit(array $source, array $candidate, array $goalOverlap): float
    {
        $must = $this->namespaceFit($source, $candidate, $goalOverlap, 'must.');
        $boundary = $this->namespaceFit($source, $candidate, $goalOverlap, 'accept.');
        return ($must * 0.75) + ($boundary * 0.25);
    }

    private function dimensionSimilarity(array $a, array $b): float
    {
        $fields = [
            'social_energy', 'communication_style', 'emotional_openness',
            'relationship_pace', 'independence_level', 'boundary_sensitivity', 'structure_vs_spontaneity',
        ];

        $sum = 0;
        $count = 0;
        foreach ($fields as $f) {
            if ($a[$f] === null || $b[$f] === null || $a[$f] === '' || $b[$f] === '') continue;
            $diff = abs((int)$a[$f] - (int)$b[$f]);
            $sum += max(0, 100 - ($diff * 25));
            $count++;
        }

        return $count > 0 ? $sum / $count : 50.0;
    }

    private function lifestyleSimilarity(array $a, array $b): float
    {
        $fields = ['smoking_preference', 'drinking_preference', 'activity_level'];
        $sum = 0;
        $count = 0;

        foreach ($fields as $f) {
            if (empty($a[$f]) || empty($b[$f])) continue;
            $sum += ($a[$f] === $b[$f]) ? 100 : 40;
            $count++;
        }

        return $count > 0 ? $sum / $count : 50.0;
    }

    private function distanceFit(array $a, array $b, int $primaryGoalId, array $source, array $candidate): float
    {
        if (($a['country_code'] ?? '') !== ($b['country_code'] ?? '')) return 0;
        $scope = (string)(($source['goal_preferences'][$primaryGoalId]['seek.location_scope'] ?? '') ?: 'all_iran');
        if ($scope === 'city_only') {
            return (($a['location_cell_l5'] ?? '') === ($b['location_cell_l5'] ?? '')) ? 100 : 0;
        }
        if (($a['location_cell_l5'] ?? '') === ($b['location_cell_l5'] ?? '')) return 100;
        if (($a['region_code'] ?? '') === ($b['region_code'] ?? '')) return 78;
        return in_array($scope, ['all_iran', 'distance_not_important', 'neighboring_provinces'], true) ? 58 : 35;
    }

    private function desiredPersonFit(array $source, array $candidate, int $goalId): float
    {
        $prefs = (array)($source['goal_preferences'][$goalId] ?? []);
        $scores = [];
        $ageRange = trim((string)($prefs['seek.age_range'] ?? ''));
        if ($ageRange !== '' && str_contains($ageRange, '-')) {
            [$min,$max] = array_map('intval', explode('-', $ageRange, 2));
            $age = (int)date('Y') - (int)($candidate['birth_year'] ?? 0);
            $scores[] = ($age >= $min && $age <= $max) ? 100.0 : 30.0;
        }
        $scores[] = (($source['interested_in_gender'] ?? '') === '' || ($source['interested_in_gender'] ?? '') === ($candidate['gender_identity'] ?? '')) ? 100.0 : 20.0;
        return $scores ? array_sum($scores) / count($scores) : 50.0;
    }

    private function confidenceScore(array $source, int $goalId): float
    {
        $prefs = (array)($source['goal_preferences'][$goalId] ?? []);
        if ($prefs === []) return 55.0;
        $filled = 0;
        foreach ($prefs as $v) if (trim((string)$v) !== '') $filled++;
        return min(100.0, max(55.0, ($filled / max(1, count($prefs))) * 100.0));
    }

    private function scheduleOverlapSymmetric(array $aSlots, array $bSlots): float
    {
        $aByDay = $this->normalizeSlotsByDay($aSlots);
        $bByDay = $this->normalizeSlotsByDay($bSlots);

        $overlapMinutes = 0;
        $totalMinutesA = 0;
        $totalMinutesB = 0;

        foreach ($aByDay as $day => $intervalsA) {
            $intervalsB = $bByDay[$day] ?? [];

            foreach ($intervalsA as $i) {
                $totalMinutesA += max(0, $i[1] - $i[0]);
            }
            foreach ($intervalsB as $j) {
                $totalMinutesB += max(0, $j[1] - $j[0]);
            }

            $i = 0;
            $j = 0;
            while ($i < count($intervalsA) && $j < count($intervalsB)) {
                $start = max($intervalsA[$i][0], $intervalsB[$j][0]);
                $end = min($intervalsA[$i][1], $intervalsB[$j][1]);
                if ($end > $start) {
                    $overlapMinutes += $end - $start;
                }

                if ($intervalsA[$i][1] <= $intervalsB[$j][1]) {
                    $i++;
                } else {
                    $j++;
                }
            }
        }

        foreach ($bByDay as $day => $intervalsB) {
            if (isset($aByDay[$day])) {
                continue;
            }
            foreach ($intervalsB as $j) {
                $totalMinutesB += max(0, $j[1] - $j[0]);
            }
        }

        $unionMinutes = max(1, $totalMinutesA + $totalMinutesB - $overlapMinutes);
        return min(100, ($overlapMinutes / $unionMinutes) * 100);
    }

    private function normalizeSlotsByDay(array $slots): array
    {
        $byDay = [];
        foreach ($slots as $slot) {
            $day = (int)($slot['weekday'] ?? -1);
            if ($day < 0 || $day > 6) {
                continue;
            }

            $start = max(0, (int)($slot['start_minute'] ?? 0));
            $end = min(24 * 60, (int)($slot['end_minute'] ?? 0));
            if ($end <= $start) {
                continue;
            }

            $byDay[$day][] = [$start, $end];
        }

        foreach ($byDay as $day => $intervals) {
            usort($intervals, static fn(array $x, array $y): int => $x[0] <=> $y[0]);
            $merged = [];
            foreach ($intervals as $current) {
                if ($merged === [] || $current[0] > $merged[count($merged) - 1][1]) {
                    $merged[] = $current;
                    continue;
                }
                $lastIdx = count($merged) - 1;
                $merged[$lastIdx][1] = max($merged[$lastIdx][1], $current[1]);
            }
            $byDay[$day] = $merged;
        }

        return $byDay;
    }

    private function mutualPreferenceFit(array $a, array $b): float
    {
        $score = 0;
        $score += ($a['interested_in_gender'] === null || $a['interested_in_gender'] === '' || $a['interested_in_gender'] === $b['gender_identity']) ? 50 : 20;
        $score += ($b['interested_in_gender'] === null || $b['interested_in_gender'] === '' || $b['interested_in_gender'] === $a['gender_identity']) ? 50 : 20;
        return $score;
    }

    private function goalSpecificFit(array $source, array $candidate, array $goalOverlap): array
    {
        $src = (array)($source['goal_preferences'] ?? []);
        $dst = (array)($candidate['goal_preferences'] ?? []);

        $matches = [];
        $gaps = [];
        $exact = 0;
        $partial = 0;
        $compared = 0;

        foreach ($goalOverlap as $goalIdRaw) {
            $goalId = (int)$goalIdRaw;
            $srcPrefs = (array)($src[$goalId] ?? []);
            $dstPrefs = (array)($dst[$goalId] ?? []);
            $keys = array_values(array_unique(array_merge(array_keys($srcPrefs), array_keys($dstPrefs))));
            foreach ($keys as $key) {
                $a = trim((string)($srcPrefs[$key] ?? ''));
                $b = trim((string)($dstPrefs[$key] ?? ''));
                if ($a === '' || $b === '') {
                    $gaps[] = 'goal:' . $goalId . ':' . $key;
                    continue;
                }
                $compared++;
                if ($a === $b) {
                    $exact++;
                    $matches[] = 'goal:' . $goalId . ':' . $key . ':exact';
                    continue;
                }
                if ($this->isNearbyPreference($a, $b)) {
                    $partial++;
                    $matches[] = 'goal:' . $goalId . ':' . $key . ':nearby';
                    continue;
                }
                $gaps[] = 'goal:' . $goalId . ':' . $key . ':mismatch';
            }
        }

        if ($compared === 0) {
            return [
                'score' => 50.0,
                'shared_goal_keys' => array_values(array_map('intval', $goalOverlap)),
                'preference_matches' => [],
                'preference_gaps' => array_slice(array_values(array_unique($gaps)), 0, 6),
            ];
        }

        $score = (($exact * 1.0) + ($partial * 0.5)) / max(1, $compared) * 100.0;

        return [
            'score' => round($score, 2),
            'shared_goal_keys' => array_values(array_map('intval', $goalOverlap)),
            'preference_matches' => array_slice(array_values(array_unique($matches)), 0, 8),
            'preference_gaps' => array_slice(array_values(array_unique($gaps)), 0, 8),
        ];
    }

    private function isNearbyPreference(string $a, string $b): bool
    {
        $pairs = [
            ['slow', 'balanced'], ['balanced', 'fast'],
            ['low', 'moderate'], ['moderate', 'high'],
            ['soft', 'balanced'], ['balanced', 'direct'],
            ['weekend', 'flexible'], ['planned', 'balanced'], ['balanced', 'spontaneous'],
            ['private', 'selective'], ['selective', 'open'],
        ];
        foreach ($pairs as [$x, $y]) {
            if (($a === $x && $b === $y) || ($a === $y && $b === $x)) {
                return true;
            }
        }
        return false;
    }
}
