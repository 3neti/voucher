<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Enums\VoucherSlicePlanMode;
use LBHurtado\Voucher\Enums\VoucherType;
use Spatie\LaravelData\Data;

final class VoucherOperationalSummaryData extends Data
{
    /**
     * @param  list<array{key: string, label: string}>  $instruction_badges
     */
    public function __construct(
        public string $capability_key,
        public string $capability_label,
        public string $voucher_type_label,
        public array $instruction_badges,
    ) {}

    public static function fromInstructions(
        VoucherInstructionsData $instructions,
        ?VoucherType $voucherType = null,
    ): self {
        $voucherType ??= $instructions->voucher_type ?? VoucherType::REDEEMABLE;
        $capability = self::capability($voucherType);
        $badges = [];

        self::appendBadge(
            $badges,
            filled($instructions->cash->validation->mobile),
            'mobile_bound',
            'Mobile-bound',
        );
        self::appendBadge(
            $badges,
            filled($instructions->cash->validation->payable),
            'vendor_bound',
            'Vendor-bound',
        );

        $usesAccountFunding = collect($instructions->claim?->outcomes ?? [])
            ->contains(fn (ClaimOutcomeInstructionData $outcome): bool => $outcome->key === 'account_funding');

        self::appendBadge($badges, $usesAccountFunding, 'account_funding', 'Account funding');

        if ($instructions->cash->settlement_rail !== null) {
            self::appendBadge(
                $badges,
                true,
                'settlement_rail',
                match ($instructions->cash->settlement_rail->value) {
                    'INSTAPAY' => 'InstaPay',
                    'PESONET' => 'PESONet',
                    default => $instructions->cash->settlement_rail->value,
                },
            );
        }

        if ($instructions->slice_plan !== null) {
            $sliceLabel = match ($instructions->slice_plan->mode) {
                VoucherSlicePlanMode::Equal => "Divisible · {$instructions->slice_plan->slices->count()} slices",
                VoucherSlicePlanMode::Flexible => 'Divisible · Flexible',
                VoucherSlicePlanMode::Scheduled => "Divisible · {$instructions->slice_plan->slices->count()} labeled slices",
            };

            self::appendBadge($badges, true, 'divisible', $sliceLabel);
        } elseif ($instructions->cash->slice_mode !== null) {
            $sliceLabel = match (true) {
                $instructions->cash->slice_mode === 'fixed' && $instructions->cash->slices !== null => "Divisible · {$instructions->cash->slices} slices",
                $instructions->cash->slice_mode === 'open' => 'Divisible · Open',
                default => 'Divisible',
            };

            self::appendBadge($badges, true, 'divisible', $sliceLabel);
        }

        foreach ($instructions->inputs->fields ?? [] as $field) {
            if ($field instanceof VoucherInputField) {
                self::appendBadge(
                    $badges,
                    true,
                    self::inputBadgeKey($field),
                    self::inputBadgeLabel($field),
                );
            }
        }

        $validation = $instructions->validation;

        self::appendBadge($badges, $validation?->otp?->required === true, 'otp', 'OTP');
        self::appendBadge($badges, $validation?->selfie?->required === true, 'selfie', 'Selfie');
        self::appendBadge($badges, $validation?->signature?->required === true, 'signature', 'Signature');
        self::appendBadge($badges, $validation?->location?->required === true, 'location', 'Location');
        self::appendBadge($badges, $validation?->face_match?->required === true, 'face_match', 'Face match');
        self::appendBadge(
            $badges,
            $validation?->time?->hasWindowValidation() === true
                || $validation?->time?->hasDurationLimit() === true,
            'time',
            'Time-bound',
        );

        return new self(
            capability_key: $capability['key'],
            capability_label: $capability['label'],
            voucher_type_label: $capability['voucher_type_label'],
            instruction_badges: $badges,
        );
    }

    /**
     * @return array{key: string, label: string, voucher_type_label: string}
     */
    private static function capability(VoucherType $voucherType): array
    {
        return match ($voucherType) {
            VoucherType::REDEEMABLE => [
                'key' => 'disbursement',
                'label' => 'Disbursement',
                'voucher_type_label' => 'Redeemable',
            ],
            VoucherType::PAYABLE => [
                'key' => 'collection',
                'label' => 'Collection',
                'voucher_type_label' => 'Payable',
            ],
            VoucherType::SETTLEMENT => [
                'key' => 'settlement',
                'label' => 'Settlement',
                'voucher_type_label' => 'Bidirectional',
            ],
        };
    }

    /**
     * @param  list<array{key: string, label: string}>  $badges
     */
    private static function appendBadge(
        array &$badges,
        bool $condition,
        string $key,
        string $label,
    ): void {
        if (! $condition || collect($badges)->contains('key', $key)) {
            return;
        }

        $badges[] = [
            'key' => $key,
            'label' => $label,
        ];
    }

    private static function inputBadgeKey(VoucherInputField $field): string
    {
        return match ($field) {
            VoucherInputField::OTP => 'otp',
            VoucherInputField::SELFIE => 'selfie',
            VoucherInputField::SIGNATURE => 'signature',
            VoucherInputField::LOCATION => 'location',
            default => 'input_'.$field->value,
        };
    }

    private static function inputBadgeLabel(VoucherInputField $field): string
    {
        return match ($field) {
            VoucherInputField::EMAIL => 'Email',
            VoucherInputField::MOBILE => 'Mobile',
            VoucherInputField::REFERENCE_CODE => 'Reference',
            VoucherInputField::SIGNATURE => 'Signature',
            VoucherInputField::KYC => 'KYC',
            VoucherInputField::NAME => 'Name',
            VoucherInputField::ADDRESS => 'Address',
            VoucherInputField::BIRTH_DATE => 'Birth Date',
            VoucherInputField::GROSS_MONTHLY_INCOME => 'Income',
            VoucherInputField::LOCATION => 'Location',
            VoucherInputField::OTP => 'OTP',
            VoucherInputField::SELFIE => 'Selfie',
        };
    }
}
