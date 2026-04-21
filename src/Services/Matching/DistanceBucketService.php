<?php

declare(strict_types=1);

namespace App\Services\Matching;

final class DistanceBucketService
{
    public function fromProfiles(array $viewer, array $candidate): string
    {
        if (($viewer['country_code'] ?? '') !== ($candidate['country_code'] ?? '')) {
            return 'same_country';
        }

        if (($viewer['region_code'] ?? '') !== ($candidate['region_code'] ?? '')) {
            return 'same_country';
        }

        if (($viewer['location_cell_l4'] ?? '') === ($candidate['location_cell_l4'] ?? '')) {
            return 'same_area';
        }

        return 'nearby_region';
    }
}
