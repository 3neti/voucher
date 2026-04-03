<?php

namespace LBHurtado\Voucher\Specifications;

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Enums\VoucherInputField;

/**
 * Validates GPS location requirements.
 *
 * Handles two scenarios:
 * 1. Location input requirement - Checks if location data was collected when required
 * 2. Geofence validation - Validates location is within required radius using Haversine formula
 */
class LocationSpecification implements RedemptionSpecificationInterface
{
    public function passes(object $voucher, RedemptionContext $context): bool
    {
        // Scenario 1: Check if location input is required
        if ($this->isLocationInputRequired($voucher)) {
            if (! $this->hasLocationData($context)) {
                return false; // Location data is required but not provided
            }
        }

        // Scenario 2: Check geofence validation (if configured)
        $locationValidation = $voucher->instructions->validation->location ?? null;

        if (! $locationValidation) {
            return true; // No geofence validation required
        }

        // Get required location and radius for geofence
        $requiredLocation = $locationValidation->coordinates ?? null;
        $requiredRadius = $locationValidation->radius ?? null;

        if (! $requiredLocation || ! $requiredRadius) {
            return true; // Incomplete geofence config, pass
        }

        // Get provided location from context (handle both nested and flat formats)
        $providedLocation = $context->inputs['location'] ?? null;

        if ($providedLocation && is_array($providedLocation)) {
            $providedLat = $providedLocation['lat'] ?? $providedLocation['latitude'] ?? null;
            $providedLng = $providedLocation['lng'] ?? $providedLocation['longitude'] ?? null;
        } else {
            // Try flat format
            $providedLat = $context->inputs['lat'] ?? $context->inputs['latitude'] ?? null;
            $providedLng = $context->inputs['lng'] ?? $context->inputs['longitude'] ?? null;
        }

        if ($providedLat === null || $providedLng === null) {
            return false; // Geofence validation requires location
        }

        // Calculate distance
        $distance = $this->calculateDistance(
            $requiredLocation['lat'],
            $requiredLocation['lng'],
            $providedLat,
            $providedLng
        );

        // Parse radius (supports "1000m" or "2km")
        $radiusMeters = $this->parseRadius($requiredRadius);

        return $distance <= $radiusMeters;
    }

    /**
     * Check if location input field is required.
     */
    private function isLocationInputRequired(object $voucher): bool
    {
        $requiredFields = $voucher->instructions->inputs->fields ?? [];

        foreach ($requiredFields as $field) {
            $fieldValue = $field instanceof VoucherInputField ? $field->value : $field;
            if ($fieldValue === 'location') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if location data exists in context.
     * Handles both nested format (location => [lat, lng]) and flat format (lat, lng at root).
     */
    private function hasLocationData(RedemptionContext $context): bool
    {
        // Check for nested location format
        $location = $context->inputs['location'] ?? null;

        if ($location && is_array($location)) {
            $lat = $location['lat'] ?? $location['latitude'] ?? null;
            $lng = $location['lng'] ?? $location['longitude'] ?? null;

            if ($lat !== null && $lng !== null) {
                return true;
            }
        }

        // Check for flat format (latitude/longitude at root level)
        $lat = $context->inputs['lat'] ?? $context->inputs['latitude'] ?? null;
        $lng = $context->inputs['lng'] ?? $context->inputs['longitude'] ?? null;

        return $lat !== null && $lng !== null;
    }

    /**
     * Calculate distance between two GPS coordinates using Haversine formula.
     *
     * @return float Distance in meters
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // Earth's radius in meters

        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLatRad = deg2rad($lat2 - $lat1);
        $deltaLngRad = deg2rad($lng2 - $lng1);

        $a = sin($deltaLatRad / 2) * sin($deltaLatRad / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLngRad / 2) * sin($deltaLngRad / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Parse radius string to meters.
     * Supports: "1000m", "2km", "500"
     */
    private function parseRadius(string $radius): float
    {
        $radius = strtolower(trim($radius));

        if (str_ends_with($radius, 'km')) {
            return (float) rtrim($radius, 'km') * 1000;
        }

        if (str_ends_with($radius, 'm')) {
            return (float) rtrim($radius, 'm');
        }

        // Assume meters if no unit
        return (float) $radius;
    }
}
