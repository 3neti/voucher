<?php

namespace LBHurtado\Voucher\Traits;

use LBHurtado\Voucher\Data\VoucherTimingData;

/**
 * Trait HasVoucherTiming
 *
 * Provides timing tracking functionality for Voucher model.
 * Tracks click, start, and submit events to measure redemption duration.
 *
 * @property array $metadata
 */
trait HasVoucherTiming
{
    /**
     * Get timing data as DTO
     */
    public function getTimingAttribute(): ?VoucherTimingData
    {
        if (! isset($this->metadata['timing'])) {
            return null;
        }

        return VoucherTimingData::from($this->metadata['timing']);
    }

    /**
     * Set timing data from DTO or array
     */
    public function setTimingAttribute(VoucherTimingData|array|null $value): void
    {
        if ($value === null) {
            $metadata = $this->metadata ?? [];
            unset($metadata['timing']);
            $this->metadata = $metadata;

            return;
        }

        $metadata = $this->metadata ?? [];
        $metadata['timing'] = $value instanceof VoucherTimingData
            ? $value->toArray()
            : $value;
        $this->metadata = $metadata;
    }

    /**
     * Track click event (idempotent - won't overwrite existing)
     */
    public function trackClick(): void
    {
        $timing = $this->timing;

        // Don't overwrite existing click
        if ($timing && $timing->clicked_at) {
            return;
        }

        $this->timing = $timing
            ? $timing->withStart() // Preserve existing data, just add click
            : VoucherTimingData::withClick();

        $this->save();
    }

    /**
     * Track redemption start
     */
    public function trackRedemptionStart(): void
    {
        $timing = $this->timing ?? VoucherTimingData::from([]);

        $this->timing = $timing->withStart();
        $this->save();
    }

    /**
     * Track redemption submission and calculate duration
     */
    public function trackRedemptionSubmit(): void
    {
        $timing = $this->timing ?? VoucherTimingData::from([]);

        $this->timing = $timing->withSubmit();
        $this->save();
    }

    /**
     * Get the duration in seconds if available
     */
    public function getRedemptionDuration(): ?int
    {
        return $this->timing?->duration_seconds;
    }

    /**
     * Check if voucher has been clicked
     */
    public function hasBeenClicked(): bool
    {
        return $this->timing?->wasClicked() ?? false;
    }

    /**
     * Check if redemption has been started
     */
    public function hasRedemptionStarted(): bool
    {
        return $this->timing?->wasStarted() ?? false;
    }
}
