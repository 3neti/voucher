<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class VoucherPaymentDetailFieldData extends Data
{
    public function __construct(public string $label, public string $value) {}
}
