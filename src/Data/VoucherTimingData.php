<?php

namespace LBHurtado\Voucher\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

/**
 * Voucher timing data for tracking lifecycle events
 *
 * Tracks when voucher was clicked, redemption started, and submitted
 * to enable duration tracking and speed-based scoring.
 *
 * @property ?string $clicked_at - ISO-8601 timestamp when voucher link was clicked
 * @property ?string $started_at - ISO-8601 timestamp when redemption wizard opened
 * @property ?string $submitted_at - ISO-8601 timestamp when redemption was submitted
 * @property ?int $duration_seconds - Time taken from start to submit (in seconds)
 */
class VoucherTimingData extends Data
{
    public function __construct(
        public ?string $clicked_at = null,
        public ?string $started_at = null,
        public ?string $submitted_at = null,
        public ?int $duration_seconds = null,
    ) {}

    public static function rules(): array
    {
        return [
            'clicked_at' => ['nullable', 'string'],
            'started_at' => ['nullable', 'string'],
            'submitted_at' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get clicked timestamp as Carbon instance
     */
    public function getClickedAt(): ?Carbon
    {
        return $this->clicked_at ? Carbon::parse($this->clicked_at) : null;
    }

    /**
     * Get started timestamp as Carbon instance
     */
    public function getStartedAt(): ?Carbon
    {
        return $this->started_at ? Carbon::parse($this->started_at) : null;
    }

    /**
     * Get submitted timestamp as Carbon instance
     */
    public function getSubmittedAt(): ?Carbon
    {
        return $this->submitted_at ? Carbon::parse($this->submitted_at) : null;
    }

    /**
     * Calculate duration if not already set
     * Returns seconds between started_at and submitted_at
     */
    public function calculateDuration(): ?int
    {
        if (! $this->started_at || ! $this->submitted_at) {
            return null;
        }

        $started = $this->getStartedAt();
        $submitted = $this->getSubmittedAt();

        return $started && $submitted ? $started->diffInSeconds($submitted) : null;
    }

    /**
     * Check if voucher was clicked
     */
    public function wasClicked(): bool
    {
        return $this->clicked_at !== null;
    }

    /**
     * Check if redemption was started
     */
    public function wasStarted(): bool
    {
        return $this->started_at !== null;
    }

    /**
     * Check if redemption was submitted
     */
    public function wasSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    /**
     * Create initial timing with click event
     */
    public static function withClick(): self
    {
        return new self(clicked_at: now()->toIso8601String());
    }

    /**
     * Create new instance with started timestamp
     */
    public function withStart(): self
    {
        return new self(
            clicked_at: $this->clicked_at,
            started_at: now()->toIso8601String(),
            submitted_at: $this->submitted_at,
            duration_seconds: $this->duration_seconds,
        );
    }

    /**
     * Create new instance with submitted timestamp and calculated duration
     */
    public function withSubmit(): self
    {
        $submitted_at = now()->toIso8601String();

        $new = new self(
            clicked_at: $this->clicked_at,
            started_at: $this->started_at,
            submitted_at: $submitted_at,
            duration_seconds: null,
        );

        // Calculate duration based on new submitted time
        return new self(
            clicked_at: $new->clicked_at,
            started_at: $new->started_at,
            submitted_at: $new->submitted_at,
            duration_seconds: $new->calculateDuration(),
        );
    }
}
