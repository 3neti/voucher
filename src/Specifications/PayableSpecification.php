<?php

namespace LBHurtado\Voucher\Specifications;

use LBHurtado\Voucher\Data\RedemptionContext;

class PayableSpecification implements RedemptionSpecificationInterface
{
    public function passes(object $voucher, RedemptionContext $context): bool
    {
        $requiredVendorAlias = $voucher->instructions->cash->validation->payable ?? null;

        // If no vendor alias is required, pass
        if ($requiredVendorAlias === null) {
            return true;
        }

        // If vendor alias is required but context doesn't have one, fail
        if ($context->vendorAlias === null) {
            return false;
        }

        // Case-insensitive comparison
        return strcasecmp($context->vendorAlias, $requiredVendorAlias) === 0;
    }
}
