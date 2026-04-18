<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

class RedemptionEvidenceData extends Data
{
    public function __construct(
        public ?string $signature = null,
        public ?string $selfie = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?array $raw = null,
    ) {}
}