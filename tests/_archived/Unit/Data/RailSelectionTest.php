<?php

use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

test('cash instruction data includes settlement rail and fee strategy', function () {
    $cashData = CashInstructionData::from([
        'amount' => 100,
        'currency' => 'PHP',
        'settlement_rail' => 'INSTAPAY',
        'fee_strategy' => 'absorb',
        'validation' => [
            'secret' => null,
            'mobile' => null,
            'country' => 'PH',
            'location' => null,
            'radius' => null,
        ],
    ]);

    expect($cashData->settlement_rail)->toBeInstanceOf(SettlementRail::class)
        ->and($cashData->settlement_rail->value)->toBe('INSTAPAY')
        ->and($cashData->fee_strategy)->toBe('absorb');
});

test('cash instruction data defaults fee strategy to absorb', function () {
    $cashData = CashInstructionData::from([
        'amount' => 100,
        'currency' => 'PHP',
        'validation' => [
            'secret' => null,
            'mobile' => null,
            'country' => 'PH',
            'location' => null,
            'radius' => null,
        ],
    ]);

    expect($cashData->fee_strategy)->toBe('absorb');
});

test('voucher instructions can include rail selection', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 60000,
            'currency' => 'PHP',
            'settlement_rail' => 'PESONET',
            'fee_strategy' => 'include',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [
            'mobile' => null,
            'email' => null,
            'webhook' => null,
        ],
        'rider' => [
            'message' => null,
            'url' => null,
        ],
        'count' => 1,
        'prefix' => null,
        'mask' => null,
        'ttl' => null,
    ]);

    expect($instructions->cash->settlement_rail->value)->toBe('PESONET')
        ->and($instructions->cash->fee_strategy)->toBe('include');
});

test('rail selection validates enum values', function () {
    expect(fn () => CashInstructionData::from([
        'amount' => 100,
        'currency' => 'PHP',
        'settlement_rail' => 'INVALID_RAIL',
        'fee_strategy' => 'absorb',
        'validation' => [
            'secret' => null,
            'mobile' => null,
            'country' => 'PH',
            'location' => null,
            'radius' => null,
        ],
    ]))->toThrow(\Spatie\LaravelData\Exceptions\CannotCastEnum::class);
});

test('fee strategy validates enum values', function () {
    $cashData = CashInstructionData::from([
        'amount' => 100,
        'currency' => 'PHP',
        'settlement_rail' => null,
        'fee_strategy' => 'invalid_strategy',
        'validation' => [
            'secret' => null,
            'mobile' => null,
            'country' => 'PH',
            'location' => null,
            'radius' => null,
        ],
    ]);

    // Should use default 'absorb' when invalid value provided
    expect($cashData->fee_strategy)->toBe('absorb');
});
