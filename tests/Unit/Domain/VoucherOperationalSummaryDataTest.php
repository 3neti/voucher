<?php

use LBHurtado\Voucher\Data\VoucherOperationalSummaryData;
use LBHurtado\Voucher\Enums\VoucherType;

it('summarizes voucher capabilities without exposing instruction values', function (
    VoucherType $voucherType,
    string $capabilityKey,
    string $capabilityLabel,
    string $voucherTypeLabel,
) {
    $instructions = validVoucherInstructions(overrides: [
        'voucher_type' => $voucherType->value,
        'target_amount' => $voucherType === VoucherType::REDEEMABLE ? null : 100,
        'cash' => [
            'validation' => [
                'mobile' => '09171234567',
                'payable' => 'TESTSHOP',
            ],
        ],
    ]);

    $summary = VoucherOperationalSummaryData::fromInstructions($instructions);
    $serialized = $summary->toArray();

    expect($summary->capability_key)->toBe($capabilityKey)
        ->and($summary->capability_label)->toBe($capabilityLabel)
        ->and($summary->voucher_type_label)->toBe($voucherTypeLabel)
        ->and($summary->instruction_badges)->toContain(
            ['key' => 'mobile_bound', 'label' => 'Mobile-bound'],
            ['key' => 'vendor_bound', 'label' => 'Vendor-bound'],
        )
        ->and(json_encode($serialized))->not->toContain('09171234567')
        ->and(json_encode($serialized))->not->toContain('TESTSHOP');
})->with([
    'redeemable' => [
        VoucherType::REDEEMABLE,
        'disbursement',
        'Disbursement',
        'Redeemable',
    ],
    'payable' => [
        VoucherType::PAYABLE,
        'collection',
        'Collection',
        'Payable',
    ],
    'settlement' => [
        VoucherType::SETTLEMENT,
        'settlement',
        'Settlement',
        'Bidirectional',
    ],
]);

it('summarizes execution-relevant instructions in deterministic badge order', function () {
    $instructions = validVoucherInstructions(overrides: [
        'cash' => [
            'settlement_rail' => 'PESONET',
            'slice_mode' => 'fixed',
            'slices' => 3,
            'validation' => [
                'mobile' => '09171234567',
            ],
        ],
        'inputs' => [
            'fields' => ['name', 'email'],
        ],
        'validation' => [
            'otp' => [
                'required' => true,
            ],
            'selfie' => [
                'required' => true,
            ],
            'signature' => [
                'required' => true,
            ],
            'location' => [
                'required' => true,
                'target_lat' => 14.5995,
                'target_lng' => 120.9842,
                'radius_meters' => 100,
                'on_failure' => 'block',
            ],
            'face_match' => [
                'required' => true,
            ],
            'time' => [
                'limit_minutes' => 10,
            ],
        ],
        'claim' => [
            'outcomes' => [
                [
                    'key' => 'account_funding',
                    'pricing_profile' => 'account-funding-v1',
                ],
            ],
            'default_outcome' => 'account_funding',
        ],
    ]);

    $summary = VoucherOperationalSummaryData::fromInstructions($instructions);

    expect($summary->instruction_badges)->toBe([
        ['key' => 'mobile_bound', 'label' => 'Mobile-bound'],
        ['key' => 'account_funding', 'label' => 'Account funding'],
        ['key' => 'settlement_rail', 'label' => 'PESONet'],
        ['key' => 'divisible', 'label' => 'Divisible · 3 slices'],
        ['key' => 'inputs', 'label' => 'Inputs · 2'],
        ['key' => 'otp', 'label' => 'OTP'],
        ['key' => 'selfie', 'label' => 'Selfie'],
        ['key' => 'signature', 'label' => 'Signature'],
        ['key' => 'location', 'label' => 'Location'],
        ['key' => 'face_match', 'label' => 'Face match'],
        ['key' => 'time', 'label' => 'Time-bound'],
    ]);
});
