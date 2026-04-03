<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use Spatie\LaravelData\Data;

/**
 * Container for all validation instructions
 *
 * Aggregates location and time validation configurations
 * for voucher redemption validation.
 *
 * @property LocationValidationData|null $location - Location validation config
 * @property TimeValidationData|null $time - Time validation config
 */
class ValidationInstructionData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public ?LocationValidationData $location = null,
        public ?TimeValidationData $time = null,
    ) {
        $this->applyRulesAndDefaults();
    }

    protected function rulesAndDefaults(): array
    {
        return [
            // No direct defaults needed - nested DTOs have their own defaults
        ];
    }

    /**
     * Check if location validation is configured
     */
    public function hasLocationValidation(): bool
    {
        return $this->location !== null;
    }

    /**
     * Check if time validation is configured
     */
    public function hasTimeValidation(): bool
    {
        return $this->time !== null;
    }

    /**
     * Check if any validation is configured
     */
    public function hasAnyValidation(): bool
    {
        return $this->hasLocationValidation() || $this->hasTimeValidation();
    }

    /**
     * Get enabled validation types
     */
    public function getEnabledValidations(): array
    {
        $enabled = [];

        if ($this->hasLocationValidation()) {
            $enabled[] = 'location';
        }

        if ($this->hasTimeValidation()) {
            $enabled[] = 'time';
        }

        return $enabled;
    }
}
