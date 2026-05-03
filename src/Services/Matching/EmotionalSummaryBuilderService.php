<?php

declare(strict_types=1);

namespace App\Services\Matching;

final class EmotionalSummaryBuilderService
{
    public function buildKey(array $scoreBreakdown, array $viewer, array $candidate): string
    {
        $schedule = (float)($scoreBreakdown['availability_fit'] ?? ($scoreBreakdown['schedule_overlap'] ?? 0));
        $distance = (float)($scoreBreakdown['distance_fit'] ?? 0);
        $dimensions = (float)($scoreBreakdown['dimensions'] ?? 0);

        if ($schedule >= 55 && $distance >= 70) {
            return 'time_and_location_aligned';
        }

        if ($dimensions >= 70 && (($viewer['boundary_sensitivity'] ?? 3) >= 3) && (($candidate['boundary_sensitivity'] ?? 3) >= 3)) {
            return 'calm_and_respectful_connection';
        }

        return 'balanced_and_low_pressure_connection';
    }
}
