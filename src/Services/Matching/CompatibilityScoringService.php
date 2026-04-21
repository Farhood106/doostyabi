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
            'mutual_preference_fit' => 10,
        ];

        $goalAlignment = min(100.0, count($goalOverlap) * 50.0);
        $dimensions = $this->dimensionSimilarity($source, $candidate);
        $lifestyle = $this->lifestyleSimilarity($source, $candidate);
        $distance = $this->distanceFit($source, $candidate);
        $schedule = $this->scheduleOverlapSymmetric($source['availability'], $candidate['availability']);
        $mutualPref = $this->mutualPreferenceFit($source, $candidate);

        $weighted =
            ($goalAlignment * $weights['goal_alignment']) +
            ($dimensions * $weights['dimensions']) +
            ($lifestyle * $weights['lifestyle']) +
            ($distance * $weights['distance_fit']) +
            ($schedule * $weights['schedule_overlap']) +
            ($mutualPref * $weights['mutual_preference_fit']);

        $score = round($weighted / 100, 2);

        $breakdown = [
            'goal_alignment' => round($goalAlignment, 2),
            'dimensions' => round($dimensions, 2),
            'lifestyle' => round($lifestyle, 2),
            'distance_fit' => round($distance, 2),
            'schedule_overlap' => round($schedule, 2),
            'mutual_preference_fit' => round($mutualPref, 2),
            'weights' => $weights,
        ];

        $reasons = [];
        if ($dimensions >= 75) $reasons[] = 'explanation.similar_core_dimensions';
        if ($schedule >= 60) $reasons[] = 'explanation.schedule_overlap_good';
        if ($goalAlignment >= 50) $reasons[] = 'explanation.shared_goal_intention';

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
        $overlapMinutes = 0;
        $totalMinutesA = 0;
        $totalMinutesB = 0;

        foreach ($aSlots as $slotA) {
            $totalMinutesA += max(0, (int)$slotA['end_minute'] - (int)$slotA['start_minute']);
            foreach ($bSlots as $slotB) {
                if ((int)$slotA['weekday'] !== (int)$slotB['weekday']) continue;
                $overlapMinutes += max(
                    0,
                    min((int)$slotA['end_minute'], (int)$slotB['end_minute'])
                    - max((int)$slotA['start_minute'], (int)$slotB['start_minute'])
                );
            }
        }

        foreach ($bSlots as $slotB) {
            $totalMinutesB += max(0, (int)$slotB['end_minute'] - (int)$slotB['start_minute']);
        }

        $unionMinutes = max(1, $totalMinutesA + $totalMinutesB - $overlapMinutes);
        return min(100, ($overlapMinutes / $unionMinutes) * 100);
    }

    private function mutualPreferenceFit(array $a, array $b): float
    {
        $score = 0;
        $score += ($a['interested_in_gender'] === null || $a['interested_in_gender'] === '' || $a['interested_in_gender'] === $b['gender_identity']) ? 50 : 20;
        $score += ($b['interested_in_gender'] === null || $b['interested_in_gender'] === '' || $b['interested_in_gender'] === $a['gender_identity']) ? 50 : 20;
        return $score;
    }
}
