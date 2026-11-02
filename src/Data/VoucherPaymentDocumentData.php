<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class VoucherPaymentDocumentData extends Data
{
    public function __construct(public string $id, public string $type, public string $label) {}
}
