<?php

namespace LBHurtado\Voucher\Support;

use ArrayObject;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Models\Voucher;

class RedemptionEvidenceExtractor
{
    public function extract(Voucher $voucher): RedemptionEvidenceData
    {
        $redeemerRecord = $voucher->redeemers()->latest('id')->first();

        $metadata = $this->normalize($redeemerRecord?->metadata);
        $redemption = Arr::get($metadata, 'redemption', []);

        return new RedemptionEvidenceData(
            signature: Arr::get($redemption, 'signature'),
            selfie: Arr::get($redemption, 'selfie'),

            latitude: $this->toNullableFloat(Arr::get($redemption, 'location.lat')),
            longitude: $this->toNullableFloat(Arr::get($redemption, 'location.lng')),

            otp: $this->toNullableString(
                Arr::get($redemption, 'otp.value', Arr::get($redemption, 'otp'))
            ),
            otp_verified: $this->toNullableBool(
                Arr::get($redemption, 'otp.verified', Arr::get($redemption, 'otp_verified'))
            ),
            otp_verified_at: $this->toNullableCarbon(
                Arr::get($redemption, 'otp.verified_at', Arr::get($redemption, 'otp_verified_at'))
            ),

            reference_code: $this->toNullableString(
                Arr::get($redemption, 'reference_code')
            ),
            mobile: $this->toNullableString(
                Arr::get($redemption, 'mobile')
            ),
            email: $this->toNullableString(
                Arr::get($redemption, 'email')
            ),
            name: $this->toNullableString(
                Arr::get($redemption, 'name')
            ),
            address: $this->toNullableString(
                Arr::get($redemption, 'address')
            ),
            birth_date: $this->toNullableString(
                Arr::get($redemption, 'birth_date')
            ),
            gross_monthly_income: $this->toNullableString(
                Arr::get($redemption, 'gross_monthly_income')
            ),

            kyc: $this->toNullableArray(
                Arr::get($redemption, 'kyc')
            ),

            face_verification_verified: $this->toNullableBool(
                Arr::get($redemption, 'kyc.face_verification.verified', Arr::get($redemption, 'face_verification.verified'))
            ),
            face_match: $this->toNullableBool(
                Arr::get($redemption, 'kyc.face_verification.face_match', Arr::get($redemption, 'face_verification.face_match'))
            ),
            match_confidence: $this->toNullableFloat(
                Arr::get($redemption, 'kyc.face_verification.match_confidence', Arr::get($redemption, 'face_verification.match_confidence'))
            ),
            face_verified_at: $this->toNullableCarbon(
                Arr::get($redemption, 'kyc.face_verification.verified_at', Arr::get($redemption, 'face_verification.verified_at'))
            ),
            face_failure_reason: $this->toNullableString(
                Arr::get($redemption, 'kyc.face_verification.failure_reason', Arr::get($redemption, 'face_verification.failure_reason'))
            ),

            redeemed_at: $this->toNullableCarbon(
                Arr::get($redemption, 'redeemed_at')
            ),

            raw: is_array($redemption) ? $redemption : [],
        );
    }

    protected function normalize(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof ArrayObject) {
            return $value->getArrayCopy();
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return [];
    }

    protected function toNullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    protected function toNullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (in_array($value, [1, '1', 'true', 'yes'], true)) {
            return true;
        }

        if (in_array($value, [0, '0', 'false', 'no'], true)) {
            return false;
        }

        return null;
    }

    protected function toNullableCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    protected function toNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== ''
            ? $value
            : null;
    }

    protected function toNullableArray(mixed $value): ?array
    {
        return is_array($value) && $value !== []
            ? $value
            : null;
    }
}