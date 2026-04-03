<?php

namespace LBHurtado\Voucher\Traits;

use LBHurtado\Voucher\Data\LocationValidationResultData;
use LBHurtado\Voucher\Data\TimeValidationResultData;
use LBHurtado\Voucher\Data\ValidationResultsData;

/**
 * Trait for managing validation results on vouchers
 *
 * Stores validation outcomes (location, time) in voucher metadata
 * after redemption validation checks are performed.
 */
trait HasValidationResults
{
    /**
     * Get validation results from metadata
     */
    public function getValidationResults(): ?ValidationResultsData
    {
        $metadata = $this->metadata['validation_results'] ?? null;

        if (! $metadata) {
            return null;
        }

        return ValidationResultsData::from($metadata);
    }

    /**
     * Set validation results in metadata
     */
    public function setValidationResults(ValidationResultsData $results): self
    {
        $metadata = $this->metadata ?? [];
        $metadata['validation_results'] = $results->toArray();
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Store validation results from individual checks
     */
    public function storeValidationResults(
        ?LocationValidationResultData $location = null,
        ?TimeValidationResultData $time = null
    ): self {
        $results = ValidationResultsData::fromValidations($location, $time);

        return $this->setValidationResults($results);
    }

    /**
     * Check if voucher has validation results
     */
    public function hasValidationResults(): bool
    {
        return $this->getValidationResults() !== null;
    }

    /**
     * Check if all validations passed
     */
    public function passedValidation(): bool
    {
        $results = $this->getValidationResults();

        return $results ? $results->allPassed() : true;
    }

    /**
     * Check if any validation failed
     */
    public function failedValidation(): bool
    {
        return ! $this->passedValidation();
    }

    /**
     * Check if validation blocked redemption
     */
    public function wasBlockedByValidation(): bool
    {
        $results = $this->getValidationResults();

        return $results ? $results->shouldBlock() : false;
    }

    /**
     * Get location validation results
     */
    public function getLocationValidationResult(): ?LocationValidationResultData
    {
        $results = $this->getValidationResults();

        return $results?->location;
    }

    /**
     * Get time validation results
     */
    public function getTimeValidationResult(): ?TimeValidationResultData
    {
        $results = $this->getValidationResults();

        return $results?->time;
    }

    /**
     * Get list of failed validation types
     */
    public function getFailedValidationTypes(): array
    {
        $results = $this->getValidationResults();

        return $results ? $results->getFailedValidations() : [];
    }

    /**
     * Get list of passed validation types
     */
    public function getPassedValidationTypes(): array
    {
        $results = $this->getValidationResults();

        return $results ? $results->getPassedValidations() : [];
    }

    /**
     * Get validation summary for display/logging
     */
    public function getValidationSummary(): array
    {
        $results = $this->getValidationResults();

        return $results ? $results->getSummary() : [
            'passed' => true,
            'blocked' => false,
            'location' => null,
            'time' => null,
        ];
    }

    /**
     * Clear validation results from metadata
     */
    public function clearValidationResults(): self
    {
        $metadata = $this->metadata ?? [];
        unset($metadata['validation_results']);
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Scope query to vouchers that passed validation
     */
    public function scopePassedValidation($query)
    {
        return $query->whereRaw("JSON_EXTRACT(metadata, '$.validation_results.passed') = true");
    }

    /**
     * Scope query to vouchers that failed validation
     */
    public function scopeFailedValidation($query)
    {
        return $query->whereRaw("JSON_EXTRACT(metadata, '$.validation_results.passed') = false");
    }

    /**
     * Scope query to vouchers blocked by validation
     */
    public function scopeBlockedByValidation($query)
    {
        return $query->whereRaw("JSON_EXTRACT(metadata, '$.validation_results.blocked') = true");
    }
}
