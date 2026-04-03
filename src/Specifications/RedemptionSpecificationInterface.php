<?php

namespace LBHurtado\Voucher\Specifications;

use LBHurtado\Voucher\Data\RedemptionContext;

interface RedemptionSpecificationInterface
{
    /**
     * Check if the redemption context satisfies this specification.
     *
     * @param  object  $voucher  Voucher with instructions property
     */
    public function passes(object $voucher, RedemptionContext $context): bool;
}
