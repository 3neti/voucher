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
            otp_verified: $this->toNullableBool(
                Arr::get($redemption, 'otp.verified', Arr::get($redemption, 'otp_verified'))
            ),
            otp_verified_at: $this->toNullableCarbon(
                Arr::get($redemption, 'otp.verified_at', Arr::get($redemption, 'otp_verified_at'))
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
}