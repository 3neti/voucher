<?php

namespace LBHurtado\Voucher\Exceptions;

use RuntimeException;

class VoucherRedemptionContractViolationException extends RuntimeException
{
    public function __construct(
        public readonly array $violations,
        string $message = 'Voucher redemption contract validation failed.'
    ) {
        parent::__construct($message);
    }
}