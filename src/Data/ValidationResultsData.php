<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

/**
 * Container for all validation results
 *
 * Aggregates location and time validation results for a voucher redemption.
 * Used to store validation outcomes in voucher metadata.
 *
 * @property LocationValidationResultData|null $location - Location validation results
 * @property TimeValidationResultData|null $time - Time validation results
 * @property bool $passed - Overall validation status
 * @property bool $blocked - Whether redemption was blocked
 */
class ValidationResultsData extends Data
{
    public function __construct(
        public ?LocationValidationResultData $location = null,
        public ?TimeValidationResultData $time = null,
        public bool $passed = true,
        public bool $blocked = false,
    ) {}

    /**
     * Create from validation checks
     */
    public static function fromValidations(
        ?LocationValidationResultData $location = null,
        ?TimeValidationResultData $time = null
    ): self {
        $locationPassed = $location ? $location->passed() : true;
        $timePassed = $time ? $time->passed() : true;

        $locationBlocked = $location ? $location->should_block : false;
        $timeBlocked = $time ? $time->should_block : false;

        return new self(
            location: $location,
            time: $time,
            passed: $locationPassed && $timePassed,
            blocked: $locationBlocked || $timeBlocked
        );
    }

    /**
     * Check if location validation was performed
     */
    public function hasLocationResults(): bool
    {
        return $this->location !== null;
    }

    /**
     * Check if time validation was performed
     */
    public function hasTimeResults(): bool
    {
        return $this->time !== null;
    }

    /**
     * Check if any validations were performed
     */
    public function hasAnyResults(): bool
    {
        return $this->hasLocationResults() || $this->hasTimeResults();
    }

    /**
     * Check if all performed validations passed
     */
    public function allPassed(): bool
    {
        return $this->passed;
    }

    /**
     * Check if any validation failed
     */
    public function anyFailed(): bool
    {
        return ! $this->passed;
    }

    /**
     * Check if redemption should be blocked
     */
    public function shouldBlock(): bool
    {
        return $this->blocked;
    }

    /**
     * Get list of failed validations
     */
    public function getFailedValidations(): array
    {
        $failed = [];

        if ($this->location && $this->location->failed()) {
            $failed[] = 'location';
        }

        if ($this->time && $this->time->failed()) {
            $failed[] = 'time';
        }

        return $failed;
    }

    /**
     * Get list of passed validations
     */
    public function getPassedValidations(): array
    {
        $passed = [];

        if ($this->location && $this->location->passed()) {
            $passed[] = 'location';
        }

        if ($this->time && $this->time->passed()) {
            $passed[] = 'time';
        }

        return $passed;
    }

    /**
     * Get validation summary
     */
    public function getSummary(): array
    {
        return [
            'passed' => $this->passed,
            'blocked' => $this->blocked,
            'location' => $this->hasLocationResults() ? [
                'validated' => $this->location->validated,
                'distance_meters' => $this->location->distance_meters,
            ] : null,
            'time' => $this->hasTimeResults() ? [
                'within_window' => $this->time->within_window,
                'within_duration' => $this->time->within_duration,
                'duration_seconds' => $this->time->duration_seconds,
            ] : null,
        ];
    }
}
