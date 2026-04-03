<?php

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Services\FeeCalculator;

beforeEach(function () {
    $this->gateway = Mockery::mock(PayoutProvider::class);
    $this->feeCalculator = new FeeCalculator($this->gateway);
});

test('absorb strategy keeps original amount', function () {
    $this->gateway->shouldReceive('getRailFee')
        ->with(Mockery::type(SettlementRail::class))
        ->andReturn(1000); // ₱10 fee

    $instructions = VoucherInstructionsData::from([
        'cash' => [
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
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['mobile' => null, 'email' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => null,
        'mask' => null,
        'ttl' => null,
    ]);

    $result = $this->feeCalculator->calculateAdjustedAmount(100, $instructions);

    expect($result['adjusted_amount'])->toBe(100.0)
        ->and($result['fee_amount'])->toBe(1000)
        ->and($result['strategy'])->toBe('absorb')
        ->and($result['rail'])->toBe('INSTAPAY');
});

test('include strategy deducts fee from amount', function () {
    $this->gateway->shouldReceive('getRailFee')
        ->with(Mockery::type(SettlementRail::class))
        ->andReturn(1000); // ₱10 fee

    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
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
        'feedback' => ['mobile' => null, 'email' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => null,
        'mask' => null,
        'ttl' => null,
    ]);

    $result = $this->feeCalculator->calculateAdjustedAmount(100, $instructions);

    expect($result['adjusted_amount'])->toBe(90.0) // 100 - 10
        ->and($result['fee_amount'])->toBe(1000)
        ->and($result['strategy'])->toBe('include');
});

test('add strategy keeps original amount and increases total cost', function () {
    $this->gateway->shouldReceive('getRailFee')
        ->with(Mockery::type(SettlementRail::class))
        ->andReturn(1000); // ₱10 fee

    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
            'fee_strategy' => 'add',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['mobile' => null, 'email' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => null,
        'mask' => null,
        'ttl' => null,
    ]);

    $result = $this->feeCalculator->calculateAdjustedAmount(100, $instructions);

    expect($result['adjusted_amount'])->toBe(100.0)
        ->and($result['total_cost'])->toBe(11000) // (100 + 10) * 100 centavos
        ->and($result['strategy'])->toBe('add');
});

test('auto-selects INSTAPAY for amounts under 50k', function () {
    $this->gateway->shouldReceive('getRailFee')
        ->with(Mockery::type(SettlementRail::class))
        ->andReturn(1000);

    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 30000,
            'currency' => 'PHP',
            'settlement_rail' => null, // Auto
            'fee_strategy' => 'absorb',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['mobile' => null, 'email' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => null,
        'mask' => null,
        'ttl' => null,
    ]);

    $result = $this->feeCalculator->calculateAdjustedAmount(30000, $instructions);

    expect($result['rail'])->toBe('INSTAPAY');
});

test('auto-selects PESONET for amounts 50k and above', function () {
    $this->gateway->shouldReceive('getRailFee')
        ->with(Mockery::type(SettlementRail::class))
        ->andReturn(2500); // ₱25 fee for PESONET

    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 75000,
            'currency' => 'PHP',
            'settlement_rail' => null, // Auto
            'fee_strategy' => 'absorb',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => ['fields' => []],
        'feedback' => ['mobile' => null, 'email' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => null,
        'mask' => null,
        'ttl' => null,
    ]);

    $result = $this->feeCalculator->calculateAdjustedAmount(75000, $instructions);

    expect($result['rail'])->toBe('PESONET');
});

test('prevents negative amount when fee exceeds voucher amount', function () {
    $this->gateway->shouldReceive('getRailFee')
        ->with(Mockery::type(SettlementRail::class))
        ->andReturn(2000); // ₱20 fee

    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 10, // Small amount
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
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
        'feedback' => ['mobile' => null, 'email' => null, 'webhook' => null],
        'rider' => ['message' => null, 'url' => null],
        'count' => 1,
        'prefix' => null,
        'mask' => null,
        'ttl' => null,
    ]);

    $result = $this->feeCalculator->calculateAdjustedAmount(10, $instructions);

    expect($result['adjusted_amount'])->toBe(0); // Clamped to 0, not negative
});
