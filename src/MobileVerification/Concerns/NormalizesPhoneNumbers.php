<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\MobileVerification\Concerns;

trait NormalizesPhoneNumbers
{
    /**
     * Normalize phone number to E.164 format.
     */
    protected function normalize(string $number): string
    {
        // Remove all non-digit characters (except leading +)
        $hasPlus = str_starts_with($number, '+');
        $digits = preg_replace('/[^0-9]/', '', $number);

        // If starts with 0, assume PH and convert to +63
        if (str_starts_with($digits, '0')) {
            return '+63' . substr($digits, 1);
        }

        // If starts with 63, add +
        if (str_starts_with($digits, '63')) {
            return '+' . $digits;
        }

        // If already has +, return as is
        if ($hasPlus) {
            return '+' . $digits;
        }

        // Default: assume PH mobile
        return '+63' . $digits;
    }
}
