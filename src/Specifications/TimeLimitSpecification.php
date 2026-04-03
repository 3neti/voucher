<?php

namespace LBHurtado\Voucher\Specifications;

use Carbon\Carbon;
use LBHurtado\Voucher\Data\RedemptionContext;

/**
 * Validates redemption time limits.
 *
 * Handles two types of time validation:
 * 1. Redemption process duration (limit_minutes) - How long the redemption process takes
 * 2. Time since creation (duration) - How long since voucher was created
 */
class TimeLimitSpecification implements RedemptionSpecificationInterface
{
    public function passes(object $voucher, RedemptionContext $context): bool
    {
        $timeValidation = $voucher->instructions->validation->time ?? null;

        if (! $timeValidation) {
            return true; // No time validation required
        }

        // Check 1: Redemption process duration limit (limit_minutes)
        if (isset($timeValidation->limit_minutes)) {
            $processDuration = $voucher->getRedemptionDuration();

            // If timing tracking failed, block redemption
            // This ensures limit_minutes validation is actually enforced
            if ($processDuration === null) {
                return false; // Cannot validate - timing data missing
            }

            $limitSeconds = $timeValidation->limit_minutes * 60;

            if ($processDuration > $limitSeconds) {
                return false; // Redemption took too long
            }
        }

        // Check 2: Time since creation (duration field)
        $duration = $timeValidation->duration ?? null;

        if ($duration) {
            $createdAt = Carbon::parse($voucher->created_at);
            $durationSeconds = $this->parseDuration($duration);
            $expiresAt = $createdAt->addSeconds($durationSeconds);

            if (Carbon::now()->greaterThan($expiresAt)) {
                return false; // Voucher has expired
            }
        }

        return true;
    }

    /**
     * Parse duration string to seconds.
     * Supports: "24h", "30m", "7d", "86400"
     */
    private function parseDuration(string $duration): int
    {
        $duration = strtolower(trim($duration));

        if (str_ends_with($duration, 'd')) {
            return (int) rtrim($duration, 'd') * 86400; // days to seconds
        }

        if (str_ends_with($duration, 'h')) {
            return (int) rtrim($duration, 'h') * 3600; // hours to seconds
        }

        if (str_ends_with($duration, 'm')) {
            return (int) rtrim($duration, 'm') * 60; // minutes to seconds
        }

        if (str_ends_with($duration, 's')) {
            return (int) rtrim($duration, 's'); // seconds
        }

        // Assume seconds if no unit
        return (int) $duration;
    }
}
