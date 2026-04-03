<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

/**
 * Result of time-based validation checks
 *
 * Contains outcomes of time window and duration validation.
 *
 * @property bool $within_window - Whether redemption is within allowed time window
 * @property bool $within_duration - Whether redemption completed within time limit
 * @property int $duration_seconds - Actual duration taken
 * @property bool $should_block - Whether to block redemption due to time violation
 */
class TimeValidationResultData extends Data
{
    public function __construct(
        public bool $within_window,
        public bool $within_duration,
        public int $duration_seconds,
        public bool $should_block,
    ) {}

    /**
     * Check if all time validations passed
     */
    public function passed(): bool
    {
        return $this->within_window && $this->within_duration;
    }

    /**
     * Check if any time validation failed
     */
    public function failed(): bool
    {
        return ! $this->passed();
    }

    /**
     * Get validation result as array
     */
    public function toArray(): array
    {
        return [
            'within_window' => $this->within_window,
            'within_duration' => $this->within_duration,
            'duration_seconds' => $this->duration_seconds,
            'should_block' => $this->should_block,
        ];
    }

    /**
     * Get duration in minutes
     */
    public function getDurationMinutes(): float
    {
        return round($this->duration_seconds / 60, 2);
    }

    /**
     * Check if window validation passed
     */
    public function passedWindowValidation(): bool
    {
        return $this->within_window;
    }

    /**
     * Check if duration validation passed
     */
    public function passedDurationValidation(): bool
    {
        return $this->within_duration;
    }
}
