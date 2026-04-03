<?php

namespace LBHurtado\Voucher\Specifications;

use Carbon\Carbon;
use LBHurtado\Voucher\Data\RedemptionContext;

/**
 * Validates redemption is within allowed time window.
 *
 * Checks if current time is between start_time and end_time.
 */
class TimeWindowSpecification implements RedemptionSpecificationInterface
{
    public function passes(object $voucher, RedemptionContext $context): bool
    {
        $timeValidation = $voucher->instructions->validation->time ?? null;

        if (! $timeValidation) {
            return true; // No time validation required
        }

        $startTime = $timeValidation->start_time ?? null;
        $endTime = $timeValidation->end_time ?? null;

        if (! $startTime || ! $endTime) {
            return true; // Incomplete config, pass
        }

        $now = Carbon::now();
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        return $now->between($start, $end);
    }
}
