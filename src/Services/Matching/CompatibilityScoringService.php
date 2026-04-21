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
        $schedule = $this->scheduleOverlap($source['availability'], $candidate['availability']);
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

        $score = 0;
        $count = 0;
        foreach ($fields as $f) {
            if ($a[$f] === null || $b[$f] === null || $a[$f] === '' || $b[$f] === '') continue;
            $diff = abs((int)$a[$f] - (int)$b[$f]);
            $score += max(0, 100 - ($diff * 25));
            $count++;
        }

        return $count > 0 ? $score / $count : 50.0;
    }

    private function lifestyleSimilarity(array $a, array $b): float
    {
        $fields = ['smoking_preference', 'drinking_preference', 'activity_level'];
        $total = 0;
        $count = 0;

        foreach ($fields as $f) {
            if (empty($a[$f]) || empty($b[$f])) continue;
            $total += ($a[$f] === $b[$f]) ? 100 : 40;
            $count++;
        }

        return $count > 0 ? $total / $count : 50.0;
    }

    private function distanceFit(array $a, array $b): float
    {
        if (($a['location_cell_l5'] ?? '') === ($b['location_cell_l5'] ?? '')) return 100;
        if (($a['location_cell_l4'] ?? '') === ($b['location_cell_l4'] ?? '')) return 70;
        return 35;
    }

    private function scheduleOverlap(array $aSlots, array $bSlots): float
    {
        $overlap = 0;
        $possible = 0;

        foreach ($aSlots as $a) {
            foreach ($bSlots as $b) {
                if ((int)$a['weekday'] !== (int)$b['weekday']) continue;
                $possible += max(0, min((int)$a['end_minute'], (int)$b['end_minute']) - max((int)$a['start_minute'], (int)$b['start_minute']));
            }
        }

        foreach ($aSlots as $a) {
            $overlap += max(1, ((int)$a['end_minute'] - (int)$a['start_minute']));
        }

        if ($overlap <= 0) return 40;

        return min(100, ($possible / $overlap) * 100);
    }

    private function mutualPreferenceFit(array $a, array $b): float
    {
        $score = 0;
        $score += ($a['interested_in_gender'] === null || $a['interested_in_gender'] === '' || $a['interested_in_gender'] === $b['gender_identity']) ? 50 : 20;
        $score += ($b['interested_in_gender'] === null || $b['interested_in_gender'] === '' || $b['interested_in_gender'] === $a['gender_identity']) ? 50 : 20;
        return $score;
    }
}
