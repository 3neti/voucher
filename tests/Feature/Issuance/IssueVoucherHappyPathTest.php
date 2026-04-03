<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\GenerateVouchers;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('issues a voucher successfully', function () {
    $instructions = validInstructions();
    $vouchers = GenerateVouchers::run($instructions);

    expect($vouchers)->toHaveCount(1);
    expect($vouchers->first()->code)->toBeString()->not->toBeEmpty();
});

it('persists lifecycle fields during issuance', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();

    expect($voucher->exists)->toBeTrue();
    expect($voucher->expires_at)->not->toBeNull();
});

it('persists amount and remaining amount during issuance', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $cash = $voucher->getEntities(\LBHurtado\Cash\Models\Cash::class)->first();

    expect($cash)->not->toBeNull();
    expect((float) $cash->amount->getAmount()->toFloat())->toBeGreaterThan(0);
});

it('persists metadata during issuance', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();

    expect($voucher->metadata)->toBeArray();
    expect($voucher->metadata)->toHaveKey('instructions');
});

it('persists instructions during issuance', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();

    expect($voucher->instructions)->not->toBeNull();
    expect($voucher->instructions->cash->amount)->toBeGreaterThan(0);
});
