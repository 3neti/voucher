<?php

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\Voucher\VoucherServiceProvider;

it('boots the voucher service provider in a minimal application', function () {
    expect(app()->getProviders(VoucherServiceProvider::class))->not->toBeEmpty();
});

it('merges default package config without published config', function () {
    expect(config('instructions'))->toBeArray();
});

it('resolves core voucher actions from the container', function () {
    expect(app(\LBHurtado\Voucher\Actions\GenerateVouchers::class))
        ->toBeInstanceOf(\LBHurtado\Voucher\Actions\GenerateVouchers::class);
});

it('resolves the compatibility execution engine from the container', function () {
    expect(app(\LBHurtado\Voucher\Services\ExecutionEngine::class))
        ->toBeInstanceOf(\LBHurtado\Voucher\Services\ExecutionEngine::class);
});

it('resolves the default execution driver from the container', function () {
    expect(app(\LBHurtado\Voucher\Contracts\ExecutionDriverContract::class))
        ->toBeInstanceOf(\LBHurtado\Voucher\Services\DefaultExecutionDriver::class);
});

it('resolves the singleton execution driver registry from the container', function () {
    $registry = app(\LBHurtado\Voucher\Services\ExecutionDriverRegistry::class);

    expect($registry)->toBe(app(\LBHurtado\Voucher\Services\ExecutionDriverRegistry::class))
        ->and($registry->resolve('default'))
        ->toBeInstanceOf(\LBHurtado\Voucher\Services\DefaultExecutionDriver::class);
});

it('boots without provider packages installed', function () {
    expect(app(PayoutProvider::class))->toBeInstanceOf(PayoutProvider::class);
});

it('boots with only emi-core contracts available', function () {
    expect(interface_exists(PayoutProvider::class))->toBeTrue();
    expect(class_exists(\LBHurtado\EmiCore\Data\PayoutRequestData::class))->toBeTrue();
    expect(class_exists(\LBHurtado\EmiCore\Data\PayoutResultData::class))->toBeTrue();
});
