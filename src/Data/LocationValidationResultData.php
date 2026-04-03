<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

/**
 * Result of location validation check
 *
 * Contains the outcome of validating a user's coordinates
 * against target location and radius.
 *
 * @property bool $validated - Whether location is within radius
 * @property float $distance_meters - Actual distance from target
 * @property bool $should_block - Whether to block redemption
 */
class LocationValidationResultData extends Data
{
    public function __construct(
        public bool $validated,
        public float $distance_meters,
        public bool $should_block,
    ) {}

    /**
     * Check if location validation passed
     */
    public function passed(): bool
    {
        return $this->validated;
    }

    /**
     * Check if location validation failed
     */
    public function failed(): bool
    {
        return ! $this->validated;
    }

    /**
     * Get validation result as array
     */
    public function toArray(): array
    {
        return [
            'validated' => $this->validated,
            'distance_meters' => $this->distance_meters,
            'should_block' => $this->should_block,
        ];
    }
}
