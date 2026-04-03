<?php

namespace LBHurtado\Voucher\Specifications;

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Enums\VoucherInputField;

/**
 * Validates KYC approval status for vouchers requiring KYC.
 *
 * Checks if:
 * 1. Voucher requires KYC (has 'kyc' in inputs.fields)
 * 2. Contact has approved KYC status (kyc_status = 'approved')
 */
class KycSpecification implements RedemptionSpecificationInterface
{
    public function passes(object $voucher, RedemptionContext $context): bool
    {
        // Check if KYC is required
        $requiredFields = $voucher->instructions->inputs->fields ?? [];

        $kycRequired = false;
        foreach ($requiredFields as $field) {
            $fieldValue = $field instanceof VoucherInputField ? $field->value : $field;
            if ($fieldValue === 'kyc') {
                $kycRequired = true;
                break;
            }
        }

        if (! $kycRequired) {
            return true; // KYC not required, pass
        }

        // Check KYC status from context inputs
        // Support both formats:
        // - 'kyc_status' => 'approved' (web flow)
        // - 'kyc' => ['status' => 'approved', ...] (bot flow)
        $kycStatus = $context->inputs['kyc_status'] ?? null;
        
        // Also check nested format from bot flow
        if ($kycStatus === null && isset($context->inputs['kyc']['status'])) {
            $kycStatus = $context->inputs['kyc']['status'];
        }

        return $kycStatus === 'approved';
    }
}
