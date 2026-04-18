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

        public ?string $otp = null,
        public ?bool $otp_verified = null,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?Carbon $otp_verified_at = null,

        public ?string $reference_code = null,
        public ?string $mobile = null,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $address = null,
        public ?string $birth_date = null,
        public ?string $gross_monthly_income = null,

        public ?array $kyc = null,

        public ?bool $face_verification_verified = null,
        public ?bool $face_match = null,
        public ?float $match_confidence = null,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?Carbon $face_verified_at = null,
        public ?string $face_failure_reason = null,

        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?Carbon $redeemed_at = null,

        public ?array $raw = null,
    ) {}
}