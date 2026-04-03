<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

class RiderInstructionData extends Data
{
    public function __construct(
        public ?string $message,
        public ?string $url,
        public ?int $redirect_timeout = null,
        public ?string $splash = null,
        public ?int $splash_timeout = null,
        public ?string $og_source = null,
    ) {}
}
