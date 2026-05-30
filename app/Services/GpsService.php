<?php

namespace App\Services;

class GpsService
{
    /**
     * Calculate the distance between two GPS coordinates using the Haversine formula.
     *
     * @return float Distance in meters
     */
    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check if two coordinates are within a given radius.
     *
     * @param float $lat1         Sitter latitude
     * @param float $lng1         Sitter longitude
     * @param float $lat2         Customer latitude
     * @param float $lng2         Customer longitude
     * @param float $radiusMeters Max allowed distance (default 500m)
     * @return array{within: bool, distance: float}
     */
    public function isWithinRadius(float $lat1, float $lng1, float $lat2, float $lng2, float $radiusMeters = 500): array
    {
        $distance = $this->calculateDistance($lat1, $lng1, $lat2, $lng2);

        return [
            'within' => $distance <= $radiusMeters,
            'distance' => round($distance),
        ];
    }

    /**
     * Get the configured check-in radius from settings.
     */
    public function getRadius(): float
    {
        $setting = \App\Models\Setting::where('key', 'gps_checkin_radius')->first();
        return $setting ? (float) $setting->value : 500;
    }
}
