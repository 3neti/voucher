<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class VoucherPresentationShareData extends Data
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $image_source = null,
    ) {}
}
