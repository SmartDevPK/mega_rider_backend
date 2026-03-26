<?php

namespace App\Services;

class RiderService  // Changed from RideService to RiderService
{
    protected array $pricing = [
        'bike' => [
            'base_fare' => 300,
            'rate_per_km' => 100,
        ],
        'car' => [
            'base_fare' => 500,
            'rate_per_km' => 200,
        ],
        'truck' => [
            'base_fare' => 1000,
            'rate_per_km' => 300,
        ],
    ];

    /**
     * Calculate delivery fees based on distance & vehicle type
     */
    public function calculateDeliveryFee(float $pickupLat, float $pickupLng, float $dropoffLat, float $dropoffLng, string $vehicleType): float
    {
        // 1. Calculate distance in km
        $distance = DistanceService::getDistance($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        // 2. Get pricing for vehicle type
        $pricing = $this->pricing[$vehicleType] ?? $this->pricing['bike'];

        // 3. Calculate total
        return $pricing['base_fare'] + ($distance * $pricing['rate_per_km']);
    }
}