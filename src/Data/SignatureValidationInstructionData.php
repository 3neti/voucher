<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

class SignatureValidationInstructionData extends Data
{
    public function __construct(
        public bool $required = false,
        public string $on_failure = 'block', // block|warn
    ) {}
}