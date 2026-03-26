<?php
namespace App\Services;

class GoogleMapsService
{
    public static function getDistanceAndDuration($pickupLat, $pickupLng, $dropoffLat, $dropoffLng)
    {
        $origin = "{$pickupLat},{$pickupLng}";
        $destination = "{$dropoffLat},{$dropoffLng}";
        $apiKey = env('GOOGLE_MAPS_API_KEY');

        $url = "https://maps.googleapis.com/maps/api/directions/json?origin=$origin&destination=$destination&key=$apiKey";
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if(empty($data['routes'])) {
            return ['distance' => 0, 'duration' => 0];
        }

        $distance = $data['routes'][0]['legs'][0]['distance']['value'] / 1000; // km
        $duration = $data['routes'][0]['legs'][0]['duration']['value'] / 60;   // minutes

        return ['distance' => $distance, 'duration' => $duration];
    }
}
