<?php

declare(strict_types=1);

namespace App\Services\Matching;

final class CompatibilityScoringService
{
    public function score(array $source, array $candidate, array $goalOverlap): array
    {
        $weights = [
            'goal_alignment' => 20,
            'dimensions' => 35,
            'lifestyle' => 10,
            'distance_fit' => 10,
            'schedule_overlap' => 15,
            'mutual_preference_fit' => 5,
            'goal_specific_fit' => 5,
        ];

        $goalAlignment = min(100.0, count($goalOverlap) * 50.0);
        $dimensions = $this->dimensionSimilarity($source, $candidate);
        $lifestyle = $this->lifestyleSimilarity($source, $candidate);
        $distance = $this->distanceFit($source, $candidate);
        $schedule = $this->scheduleOverlapSymmetric($source['availability'], $candidate['availability']);
        $mutualPref = $this->mutualPreferenceFit($source, $candidate);
        $goalSpecific = $this->goalSpecificFit($source, $candidate, $goalOverlap);

        $weighted =
            ($goalAlignment * $weights['goal_alignment']) +
            ($dimensions * $weights['dimensions']) +
            ($lifestyle * $weights['lifestyle']) +
            ($distance * $weights['distance_fit']) +
            ($schedule * $weights['schedule_overlap']) +
            ($mutualPref * $weights['mutual_preference_fit']) +
            ($goalSpecific['score'] * $weights['goal_specific_fit']);

        $score = round($weighted / 100, 2);

        $breakdown = [
            'goal_alignment' => round($goalAlignment, 2),
            'dimensions' => round($dimensions, 2),
            'lifestyle' => round($lifestyle, 2),
            'distance_fit' => round($distance, 2),
            'schedule_overlap' => round($schedule, 2),
            'mutual_preference_fit' => round($mutualPref, 2),
            'goal_specific_fit' => round((float)$goalSpecific['score'], 2),
            'shared_goal_keys' => $goalSpecific['shared_goal_keys'],
            'preference_matches' => $goalSpecific['preference_matches'],
            'preference_gaps' => $goalSpecific['preference_gaps'],
            'weights' => $weights,
        ];

        $reasons = [];
        if ($dimensions >= 75) $reasons[] = 'explanation.similar_core_dimensions';
        if ($schedule >= 60) $reasons[] = 'explanation.schedule_overlap_good';
        if ($goalAlignment >= 50) $reasons[] = 'explanation.shared_goal_intention';
        if ($distance >= 70) $reasons[] = 'explanation.location_proximity_good';
        if ($mutualPref >= 80) $reasons[] = 'explanation.mutual_preference_fit_good';
        if ((float)$goalSpecific['score'] >= 75) $reasons[] = 'explanation.goal_specific_alignment_good';
        if ((float)$goalSpecific['score'] >= 45 && (float)$goalSpecific['score'] < 75) $reasons[] = 'explanation.goal_specific_alignment_partial';

        $cautions = [];
        if ($lifestyle < 45) $cautions[] = 'explanation.caution_lifestyle_gap';
        if ($distance < 50) $cautions[] = 'explanation.caution_distance';

        return [
            'compatibility_score' => $score,
            'score_breakdown' => $breakdown,
            'top_match_reasons' => $reasons,
            'caution_points' => $cautions,
        ];
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
