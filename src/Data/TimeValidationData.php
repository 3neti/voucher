<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use Spatie\LaravelData\Data;

/**
 * Time-based validation configuration
 *
 * Defines time window restrictions and duration tracking for redemptions.
 * Can enforce time-of-day windows and maximum completion durations.
 *
 * @property TimeWindowData|null $window - Time window for allowed redemptions
 * @property int|null $limit_minutes - Max minutes to complete redemption
 * @property bool $track_duration - Whether to track redemption duration
 */
class TimeValidationData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public ?TimeWindowData $window = null,
        public ?int $limit_minutes = null,
        public bool $track_duration = true,
    ) {
        $this->applyRulesAndDefaults();
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'limit_minutes' => [
                ['nullable', 'integer', 'min:1', 'max:1440'], // max 24 hours
                config('instructions.validation.time.default_limit_minutes', null),
            ],
            'track_duration' => [
                ['required', 'boolean'],
                config('instructions.validation.time.track_duration', true),
            ],
        ];
    }

    /**
     * Check if time window validation is enabled
     */
    public function hasWindowValidation(): bool
    {
        return $this->window !== null;
    }

    /**
     * Check if duration limit is enabled
     */
    public function hasDurationLimit(): bool
    {
        return $this->limit_minutes !== null;
    }

    /**
     * Check if duration tracking is enabled
     */
    public function shouldTrackDuration(): bool
    {
        return $this->track_duration;
    }

    /**
     * Validate if current time is within window
     */
    public function isWithinWindow(): bool
    {
        if (! $this->hasWindowValidation()) {
            return true; // No window = always valid
        }

        return $this->window->isWithinWindow();
    }

    /**
     * Check if duration exceeds limit
     */
    public function exceedsDurationLimit(int $durationSeconds): bool
    {
        if (! $this->hasDurationLimit()) {
            return false; // No limit = never exceeds
        }

        $limitSeconds = $this->limit_minutes * 60;

        return $durationSeconds > $limitSeconds;
    }
}
