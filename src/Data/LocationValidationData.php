<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use Spatie\LaravelData\Data;

/**
 * Location validation configuration for geo-fencing
 *
 * Defines target coordinates and radius for location-based validation.
 * Used to enforce that redemptions happen at specific locations.
 *
 * @property bool $required - Whether location validation is required
 * @property float $target_lat - Target latitude (-90 to 90)
 * @property float $target_lng - Target longitude (-180 to 180)
 * @property int $radius_meters - Acceptable radius in meters
 * @property string $on_failure - Action on failure: 'block' or 'warn'
 */
class LocationValidationData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public bool $required,
        public ?float $target_lat,
        public ?float $target_lng,
        public ?int $radius_meters,
        public string $on_failure = 'block',
    ) {
        $this->applyRulesAndDefaults();
    }

    public static function rules(): array
    {
        return [
            'required' => ['required', 'boolean'],
            'target_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'target_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'on_failure' => ['required', 'in:block,warn'],
        ];
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'required' => [
                ['required', 'boolean'],
                config('instructions.validation.location.required', true),
            ],
            'radius_meters' => [
                ['required', 'integer', 'min:1'],
                config('instructions.validation.location.default_radius_meters', 50),
            ],
            'on_failure' => [
                ['required', 'in:block,warn'],
                config('instructions.validation.location.on_failure', 'block'),
            ],
        ];
    }

    /**
     * Validate user's location against target coordinates
     * Returns LocationValidationResultData
     */
    public function validateLocation(float $userLat, float $userLng): LocationValidationResultData
    {
        $distance = $this->calculateDistance($userLat, $userLng);
        $withinRadius = $distance <= $this->radius_meters;

        return LocationValidationResultData::from([
            'validated' => $withinRadius,
            'distance_meters' => round($distance, 2),
            'should_block' => ! $withinRadius && $this->on_failure === 'block',
        ]);
    }

    /**
     * Calculate distance using Haversine formula
     * Returns distance in meters
     */
    protected function calculateDistance(float $lat, float $lng): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($this->target_lat - $lat);
        $dLon = deg2rad($this->target_lng - $lng);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat)) * cos(deg2rad($this->target_lat)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
