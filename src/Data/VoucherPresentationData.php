<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class VoucherPresentationData extends Data
{
    public function __construct(
        public ?VoucherPresentationStampData $stamp = null,
        public ?VoucherPresentationShareData $share = null,
    ) {}
}
