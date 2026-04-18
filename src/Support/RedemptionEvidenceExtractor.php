<?php

namespace LBHurtado\Voucher\Support;

use ArrayObject;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
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
}