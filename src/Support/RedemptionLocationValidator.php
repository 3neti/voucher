<?php

namespace LBHurtado\Voucher\Support;

class RedemptionLocationValidator
{
    public function isWithinRadius(
        float $currentLat,
        float $currentLng,
        float $targetLat,
        float $targetLng,
        int $radiusMeters
    ): bool {
        return $this->distanceMeters($currentLat, $currentLng, $targetLat, $targetLng) <= $radiusMeters;
    }

    public function distanceMeters(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}