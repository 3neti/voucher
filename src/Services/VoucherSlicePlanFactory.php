<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Services;

use InvalidArgumentException;
use LBHurtado\Voucher\Data\VoucherSlicePlanData;
use LBHurtado\Voucher\Enums\VoucherSlicePlanMode;
use LBHurtado\Voucher\Enums\VoucherSliceSelectionPolicy;

final class VoucherSlicePlanFactory
{
    /**
     * @param  array<int, string>  $labels
     */
    public function equal(int $totalMinor, string $currency, int $count, array $labels = []): VoucherSlicePlanData
    {
        if ($count < 2 || $totalMinor < $count) {
            throw new InvalidArgumentException('Equal slice plans require at least two slices of one minor unit each.');
        }

        $base = intdiv($totalMinor, $count);
        $remainder = $totalMinor % $count;
        $slices = [];

        for ($index = 0; $index < $count; $index++) {
            $sequence = $index + 1;
            $label = trim((string) ($labels[$index] ?? ''));

            $slices[] = [
                'id' => "slice_{$sequence}",
                'label' => $label !== '' ? $label : "Slice {$sequence}",
                'amount_minor' => $base + ($index < $remainder ? 1 : 0),
                'sequence' => $sequence,
            ];
        }

        return VoucherSlicePlanData::from([
            'schema' => VoucherSlicePlanData::SCHEMA,
            'mode' => VoucherSlicePlanMode::Equal->value,
            'selection' => VoucherSliceSelectionPolicy::NextOnly->value,
            'total_minor' => $totalMinor,
            'currency' => strtoupper($currency),
            'slices' => $slices,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $slices
     */
    public function scheduled(
        int $totalMinor,
        string $currency,
        array $slices,
        VoucherSliceSelectionPolicy $selection = VoucherSliceSelectionPolicy::OneOrMany,
    ): VoucherSlicePlanData {
        if (! in_array($selection, [VoucherSliceSelectionPolicy::One, VoucherSliceSelectionPolicy::OneOrMany], true)) {
            throw new InvalidArgumentException('Scheduled slice plans require one or one_or_many selection.');
        }

        $normalized = array_values(array_map(
            static function (array $slice, int $index): array {
                $sequence = $index + 1;
                $label = trim((string) ($slice['label'] ?? $slice['description'] ?? ''));

                return [
                    'id' => trim((string) ($slice['id'] ?? '')) ?: "slice_{$sequence}",
                    'label' => $label !== '' ? $label : "Slice {$sequence}",
                    'amount_minor' => $slice['amount_minor'] ?? null,
                    'sequence' => $sequence,
                    'claim_on' => $slice['claim_on'] ?? null,
                    'claim_by' => $slice['claim_by'] ?? null,
                ];
            },
            $slices,
            array_keys($slices),
        ));

        return VoucherSlicePlanData::from([
            'schema' => VoucherSlicePlanData::SCHEMA,
            'mode' => VoucherSlicePlanMode::Scheduled->value,
            'selection' => $selection->value,
            'total_minor' => $totalMinor,
            'currency' => strtoupper($currency),
            'slices' => $normalized,
        ]);
    }

    public function flexible(
        int $totalMinor,
        string $currency,
        int $maxSlices,
        int $minAmountMinor,
    ): VoucherSlicePlanData {
        return VoucherSlicePlanData::from([
            'schema' => VoucherSlicePlanData::SCHEMA,
            'mode' => VoucherSlicePlanMode::Flexible->value,
            'selection' => VoucherSliceSelectionPolicy::FlexibleAmount->value,
            'total_minor' => $totalMinor,
            'currency' => strtoupper($currency),
            'slices' => [],
            'max_slices' => $maxSlices,
            'min_amount_minor' => $minAmountMinor,
        ]);
    }
}
