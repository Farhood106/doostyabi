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
        $primaryGoalFit = $primaryInOverlap ? min(100.0, 75.0 + ($secondaryOverlapCount * 10.0)) : 35.0;

        $needOffer = $this->needOfferFit($source, $candidate, $goalOverlap, $primaryGoalId);
        $privacyFit = $this->namespaceFit($source, $candidate, $goalOverlap, 'privacy.');
        $paceDurationFit = $this->namespaceFit($source, $candidate, $goalOverlap, 'pace.', 'duration.');
        $boundaryConsentFit = $this->boundaryConsentFit($source, $candidate, $goalOverlap);
        $availabilityDistanceFit = (($this->distanceFit($source, $candidate) * 0.6) + ($this->scheduleOverlapSymmetric($source['availability'], $candidate['availability']) * 0.4));
        $personalityOptionalFit = (($this->dimensionSimilarity($source, $candidate) * 0.8) + ($this->lifestyleSimilarity($source, $candidate) * 0.2));
        $penalties = $needOffer['penalties'];

        $weights = [
            'primary_goal_fit' => 24,
            'need_offer_fit' => 26,
            'privacy_fit' => 12,
            'pace_duration_fit' => 10,
            'boundary_consent_fit' => 16,
            'availability_distance_fit' => 10,
            'personality_optional_fit' => 2,
        ];
        $weighted =
            ($primaryGoalFit * $weights['primary_goal_fit']) +
            ($needOffer['score'] * $weights['need_offer_fit']) +
            ($privacyFit * $weights['privacy_fit']) +
            ($paceDurationFit * $weights['pace_duration_fit']) +
            ($boundaryConsentFit * $weights['boundary_consent_fit']) +
            ($availabilityDistanceFit * $weights['availability_distance_fit']) +
            ($personalityOptionalFit * $weights['personality_optional_fit']);
        $score = round(max(0.0, ($weighted / 100.0) - $penalties), 2);

        $breakdown = [
            'primary_goal_fit' => round($primaryGoalFit, 2),
            'need_offer_fit' => round((float)$needOffer['score'], 2),
            'privacy_fit' => round($privacyFit, 2),
            'pace_duration_fit' => round($paceDurationFit, 2),
            'boundary_consent_fit' => round($boundaryConsentFit, 2),
            'availability_distance_fit' => round($availabilityDistanceFit, 2),
            'personality_optional_fit' => round($personalityOptionalFit, 2),
            'penalties' => round($penalties, 2),
            'preference_matches' => $needOffer['matches'],
            'preference_gaps' => $needOffer['gaps'],
            'weights' => $weights,
        ];

        $reasons = [];
        if ($primaryGoalFit >= 65) $reasons[] = 'explanation.shared_purpose';
        if ($needOffer['score'] >= 65) $reasons[] = 'explanation.need_offer_alignment';
        if ($privacyFit >= 65) $reasons[] = 'explanation.privacy_alignment';
        if ($boundaryConsentFit >= 65) $reasons[] = 'explanation.boundary_alignment';
        if ($paceDurationFit >= 65) $reasons[] = 'explanation.pace_duration_alignment';

        $cautions = [];
        if ($needOffer['score'] < 50 || $privacyFit < 50 || $boundaryConsentFit < 50) $cautions[] = 'explanation.caution_expectation_gap';
        if ($availabilityDistanceFit < 50) $cautions[] = 'explanation.caution_distance';

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

    private function distanceFit(array $a, array $b): float
    {
        if (($a['country_code'] ?? '') !== ($b['country_code'] ?? '')) return 0;
        if (($a['region_code'] ?? '') !== ($b['region_code'] ?? '')) return 45;
        if (($a['location_cell_l5'] ?? '') === ($b['location_cell_l5'] ?? '')) return 100;
        if (($a['location_cell_l4'] ?? '') === ($b['location_cell_l4'] ?? '')) return 75;
        return 55;
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
