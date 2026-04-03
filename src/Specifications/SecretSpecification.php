<?php

namespace LBHurtado\Voucher\Specifications;

use LBHurtado\Voucher\Data\RedemptionContext;

class SecretSpecification implements RedemptionSpecificationInterface
{
    public function passes(object $voucher, RedemptionContext $context): bool
    {
        $requiredSecret = $voucher->instructions->cash->validation->secret ?? null;

        // If no secret is required, pass
        if ($requiredSecret === null) {
            return true;
        }

        // If secret is required but context doesn't have one, fail
        if ($context->secret === null) {
            return false;
        }

        // Compare secrets
        return $context->secret === $requiredSecret;
    }
}
