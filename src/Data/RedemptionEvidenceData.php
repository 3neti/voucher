<?php

namespace LBHurtado\Voucher\Data;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class RedemptionEvidenceData extends Data
{
    public function __construct(
        public ?string $signature = null,
        public ?string $selfie = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?bool $otp_verified = null,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?Carbon $otp_verified_at = null,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?Carbon $redeemed_at = null,
        public ?array $raw = null,
    ) {}
}