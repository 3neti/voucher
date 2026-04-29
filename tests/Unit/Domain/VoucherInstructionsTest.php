<?php

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('persists instruction payloads in order', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 150.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'inputs' => [
                'fields' => ['name', 'email', 'birth_date'],
            ],
        ],
    ));

    $fields = $voucher->metadata['instructions']['inputs']['fields'] ?? [];

    expect($fields)->toBe(['name', 'email', 'birth_date']);
});

it('allows empty instructions when configured', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'inputs' => [
                'fields' => [],
            ],
        ],
    ));

    expect($voucher->instructions->inputs->fields ?? [])->toBeArray()
        ->and($voucher->instructions->inputs->fields ?? [])->toBeEmpty();
});

it('rejects invalid instruction payload structures', function () {
    expect(fn () => VoucherInstructionsData::from([
        'cash' => 'not-an-array',
        'feedback' => 'also-invalid',
    ]))->toThrow(ValidationException::class);
});

it('preserves pricing and flags within instructions metadata', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 275.50,
        settlementRail: 'PESONET',
        overrides: [
            'cash' => [
                'fee_strategy' => 'absorb',
                'slice_mode' => 'open',
                'min_withdrawal' => 50,
            ],
        ],
    ));

    $cash = $voucher->metadata['instructions']['cash'] ?? [];

    expect((float) ($cash['amount'] ?? 0))->toBe(275.5)
        ->and($cash['settlement_rail'] ?? null)->toBe('PESONET')
        ->and($cash['fee_strategy'] ?? null)->toBe('absorb')
        ->and($cash['slice_mode'] ?? null)->toBe('open')
        ->and((float) ($cash['min_withdrawal'] ?? 0))->toBe(50.0);
});

it('preserves metadata flow_type through createFromAttribs and toCleanArray', function () {
    $instructions = VoucherInstructionsData::createFromAttribs([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'country' => 'PH',
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
        'metadata' => [
            'flow_type' => 'collectible',
        ],
    ]);

    expect($instructions->metadata)->not->toBeNull()
        ->and($instructions->metadata->flow_type)->toBe('collectible')
        ->and(data_get($instructions->toCleanArray(), 'metadata.flow_type'))->toBe('collectible');
});