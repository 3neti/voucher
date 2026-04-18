<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

class LocationValidationInstructionData extends Data
{
    public function __construct(
        public bool $required = false,
        public ?float $target_lat = null,
        public ?float $target_lng = null,
        public ?int $radius_meters = null,
        public string $on_failure = 'block', // block|warn
    ) {}
}