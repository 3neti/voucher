<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class VoucherPresentationStampData extends Data
{
    public function __construct(
        public string $design_id,
        public int $design_version,
        public ?bool $show_logo = null,
        public ?bool $show_tagline = null,
        public ?string $claim_marker = null,
    ) {}
}
