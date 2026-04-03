<?php

namespace LBHurtado\Voucher\Specifications;

use LBHurtado\Voucher\Data\RedemptionContext;

class MobileSpecification implements RedemptionSpecificationInterface
{
    public function passes(object $voucher, RedemptionContext $context): bool
    {
        $requiredMobile = $voucher->instructions->cash->validation->mobile ?? null;

        // If no mobile is required, pass
        if ($requiredMobile === null) {
            return true;
        }

        // Normalize both numbers to E.164 format for comparison
        $normalizedRequired = $this->normalize($requiredMobile);
        $normalizedContext = $this->normalize($context->mobile);

        return $normalizedContext === $normalizedRequired;
    }

    /**
     * Normalize phone number to E.164 format
     */
    private function normalize(string $number): string
    {
        // Remove all non-digit characters
        $digits = preg_replace('/[^0-9]/', '', $number);

        // If starts with 0, assume PH and convert to +63
        if (str_starts_with($digits, '0')) {
            return '+63'.substr($digits, 1);
        }

        // If starts with 63, add +
        if (str_starts_with($digits, '63')) {
            return '+'.$digits;
        }

        // If already has +, return as is
        if (str_starts_with($number, '+')) {
            return '+'.$digits;
        }

        // Default: assume PH mobile
        return '+63'.$digits;
    }
}
