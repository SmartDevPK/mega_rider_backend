<?php

namespace App\Services;

class DistanceService
{
    /**
     * Calculate straight-line distance in km between two coordinates
     */
    public static function getDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km

        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLng / 2) * sin($deltaLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Estimate travel time based on average speed (in km/h)
     */
    public static function estimateTime(float $distance, float $averageSpeed = 40): float
    {
        // Returns time in minutes
        return ($distance / $averageSpeed) * 60;
    }
}
