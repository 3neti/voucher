<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class VoucherPricingDecisionData extends Data
{
    public function __construct(
        public string $quote_reference,
        public string $currency,
        public string $fee_bearer,
        public ?string $quote_hash = null,
    ) {}
}
