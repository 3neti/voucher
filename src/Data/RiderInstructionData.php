<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Enums\RiderContentFormat;
use Spatie\LaravelData\Data;

class RiderInstructionData extends Data
{
    public function __construct(
        public ?string $message,
        public ?string $url,
        public ?int $redirect_timeout = null,
        public ?string $splash = null,
        public ?int $splash_timeout = null,
        public ?array $splash_meta = null,
        public ?string $og_source = null,
        public ?RiderContentFormat $message_format = null,
        public ?RiderContentFormat $splash_format = null,
        public ?RiderStampData $stamp = null,
    ) {}
}
