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
        $inputs = Arr::get($metadata, 'inputs', []);

        $otpRaw = $this->firstPresent(
            Arr::get($redemption, 'otp.value'),
            is_scalar(Arr::get($redemption, 'otp')) ? Arr::get($redemption, 'otp') : null,

            Arr::get($inputs, 'otp.value'),
            Arr::get($inputs, 'otp.otp_code'),
            is_scalar(Arr::get($inputs, 'otp')) ? Arr::get($inputs, 'otp') : null,
            Arr::get($inputs, 'otp_code'),
        );

        $otpVerifiedRaw = $this->firstPresent(
            Arr::get($redemption, 'otp.verified'),
            Arr::get($redemption, 'otp_verified'),

            Arr::get($inputs, 'otp.verified'),
            Arr::get($inputs, 'otp_verified'),
            Arr::get($inputs, 'verified'),
        );

        $otpVerifiedAtRaw = $this->firstPresent(
            Arr::get($redemption, 'otp.verified_at'),
            Arr::get($redemption, 'otp_verified_at'),

            Arr::get($inputs, 'otp.verified_at'),
            Arr::get($inputs, 'verified_at'),
        );

        return new RedemptionEvidenceData(
            signature: $this->toNullableString(
                $this->firstPresent(
                    Arr::get($redemption, 'signature'),
                    Arr::get($inputs, 'signature'),
                )
            ),

            selfie: $this->toNullableString(
                $this->firstPresent(
                    Arr::get($redemption, 'selfie'),
                    Arr::get($inputs, 'selfie'),
                )
            ),

            latitude: $this->toNullableFloat(
                $this->firstPresent(
                    Arr::get($redemption, 'location.lat'),
                    Arr::get($inputs, 'location.lat'),
                    Arr::get($inputs, 'latitude'),
                )
            ),

            longitude: $this->toNullableFloat(
                $this->firstPresent(
                    Arr::get($redemption, 'location.lng'),
                    Arr::get($inputs, 'location.lng'),
                    Arr::get($inputs, 'longitude'),
                )
            ),

            otp: $this->toNullableString($otpRaw),

            otp_verified: $otpVerifiedRaw !== null
                ? $this->toNullableBool($otpVerifiedRaw)
                : ($otpVerifiedAtRaw !== null ? true : null),

            otp_verified_at: $this->toNullableCarbon($otpVerifiedAtRaw),

            reference_code: $this->toNullableString(
                $this->firstPresent(
                    Arr::get($redemption, 'reference_code'),
                    Arr::get($inputs, 'reference_code'),
                )
            ),

            mobile: $this->toNullableString(
                $this->firstPresent(
                    Arr::get($redemption, 'mobile'),
                    Arr::get($inputs, 'mobile'),
                )
            ),

            email: $this->toNullableString(
                $this->firstPresent(
                    Arr::get($redemption, 'email'),
                    Arr::get($inputs, 'email'),
                )
            ),

            name: $this->toNullableString(
                $this->firstPresent(
                    Arr::get($redemption, 'name'),
                    Arr::get($inputs, 'name'),
                    Arr::get($inputs, 'full_name'),
                )
            ),

            address: $this->toNullableString(
                $this->firstPresent(
                    Arr::get($redemption, 'address'),
                    Arr::get($inputs, 'address'),
                )
            ),

            birth_date: $this->toNullableString(
                $this->firstPresent(
                    Arr::get($redemption, 'birth_date'),
                    Arr::get($inputs, 'birth_date'),
                )
            ),

            gross_monthly_income: $this->toNullableString(
                $this->firstPresent(
                    Arr::get($redemption, 'gross_monthly_income'),
                    Arr::get($inputs, 'gross_monthly_income'),
                )
            ),

            kyc: $this->toNullableArray(
                $this->firstPresent(
                    Arr::get($redemption, 'kyc'),
                    Arr::get($inputs, 'kyc'),
                )
            ),

            face_verification_verified: $this->toNullableBool(
                $this->firstPresent(
                    Arr::get($redemption, 'kyc.face_verification.verified'),
                    Arr::get($redemption, 'face_verification.verified'),
                    Arr::get($inputs, 'kyc.face_verification.verified'),
                )
            ),

            face_match: $this->toNullableBool(
                $this->firstPresent(
                    Arr::get($redemption, 'kyc.face_verification.face_match'),
                    Arr::get($redemption, 'face_verification.face_match'),
                    Arr::get($inputs, 'kyc.face_verification.face_match'),
                )
            ),

            match_confidence: $this->toNullableFloat(
                $this->firstPresent(
                    Arr::get($redemption, 'kyc.face_verification.match_confidence'),
                    Arr::get($redemption, 'face_verification.match_confidence'),
                    Arr::get($inputs, 'kyc.face_verification.match_confidence'),
                )
            ),

            face_verified_at: $this->toNullableCarbon(
                $this->firstPresent(
                    Arr::get($redemption, 'kyc.face_verification.verified_at'),
                    Arr::get($redemption, 'face_verification.verified_at'),
                    Arr::get($inputs, 'kyc.face_verification.verified_at'),
                )
            ),

            face_failure_reason: $this->toNullableString(
                $this->firstPresent(
                    Arr::get($redemption, 'kyc.face_verification.failure_reason'),
                    Arr::get($redemption, 'face_verification.failure_reason'),
                    Arr::get($inputs, 'kyc.face_verification.failure_reason'),
                )
            ),

            redeemed_at: $this->toNullableCarbon(
                $this->firstPresent(
                    Arr::get($redemption, 'redeemed_at'),
                    Arr::get($inputs, 'redeemed_at'),
                )
            ),

            raw: [
                'redemption' => is_array($redemption) ? $redemption : [],
                'inputs' => is_array($inputs) ? $inputs : [],
            ],
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

    protected function firstPresent(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if ($value !== null) {
                return $value;
            }
        }

        return null;
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
        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return null;
    }

    protected function toNullableArray(mixed $value): ?array
    {
        return is_array($value) && $value !== []
            ? $value
            : null;
    }
}