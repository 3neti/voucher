<?php

namespace LBHurtado\Voucher\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

/**
 * Time window configuration for time-of-day validation
 *
 * Defines a daily time window when redemptions are allowed.
 * Supports cross-midnight windows (e.g., 22:00 to 02:00).
 *
 * @property string $start_time - Start time in H:i format (e.g., "09:00")
 * @property string $end_time - End time in H:i format (e.g., "17:00")
 * @property string $timezone - Timezone for time comparison
 */
class TimeWindowData extends Data
{
    public function __construct(
        public string $start_time,
        public string $end_time,
        public string $timezone = 'Asia/Manila',
    ) {}

    /**
     * Check if current time is within the window
     */
    public function isWithinWindow(?Carbon $time = null): bool
    {
        $now = $time ?? Carbon::now($this->timezone);

        $start = $this->parseTime($this->start_time, $now);
        $end = $this->parseTime($this->end_time, $now);

        // Handle cross-midnight windows (e.g., 22:00 to 02:00)
        if ($end->lessThan($start)) {
            // Window spans midnight
            return $now->greaterThanOrEqualTo($start) || $now->lessThanOrEqualTo($end);
        }

        // Normal window within same day
        return $now->between($start, $end, true);
    }

    /**
     * Parse time string (H:i) and apply to given date
     */
    protected function parseTime(string $time, Carbon $date): Carbon
    {
        [$hour, $minute] = explode(':', $time);

        return $date->copy()
            ->setTime((int) $hour, (int) $minute, 0);
    }

    /**
     * Get start time as Carbon instance
     */
    public function getStartTime(?Carbon $date = null): Carbon
    {
        $date = $date ?? Carbon::now($this->timezone);

        return $this->parseTime($this->start_time, $date);
    }

    /**
     * Get end time as Carbon instance
     */
    public function getEndTime(?Carbon $date = null): Carbon
    {
        $date = $date ?? Carbon::now($this->timezone);

        return $this->parseTime($this->end_time, $date);
    }

    /**
     * Check if window spans midnight
     */
    public function spansMidnight(): bool
    {
        $date = Carbon::now($this->timezone);
        $start = $this->getStartTime($date);
        $end = $this->getEndTime($date);

        return $end->lessThan($start);
    }
}
