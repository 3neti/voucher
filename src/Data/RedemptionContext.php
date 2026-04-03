<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

class RedemptionContext extends Data
{
    public function __construct(
        public string $mobile,
        public ?string $secret = null,
        public ?string $vendorAlias = null,
        public array $inputs = [],
        public array $bankAccount = [],
    ) {}
}
