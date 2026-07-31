<?php

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Contracts\SettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Contracts\StoredValueExecutionGateway;
use LBHurtado\Voucher\Services\DefaultExecutionDriver;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Voucher\Services\ExecutionPipelineRuntime;
use LBHurtado\Voucher\Services\ExecutionPipelineStepRegistry;
use LBHurtado\Voucher\Services\NullSettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Services\NullStoredValueExecutionGateway;
use LBHurtado\Voucher\Services\SettlementEnvelopeExecutionDriver;
use LBHurtado\Voucher\Services\StoredValueExecutionDriver;
use LBHurtado\Voucher\VoucherServiceProvider;

it('boots the voucher service provider in a minimal application', function () {
    expect(app()->getProviders(VoucherServiceProvider::class))->not->toBeEmpty();
});

it('merges default package config without published config', function () {
    expect(config('instructions'))->toBeArray();
});

it('resolves core voucher actions from the container', function () {
    expect(app(GenerateVouchers::class))
        ->toBeInstanceOf(GenerateVouchers::class);
});

it('resolves the compatibility execution engine from the container', function () {
    expect(app(ExecutionEngine::class))
        ->toBeInstanceOf(ExecutionEngine::class);
});

it('resolves the default execution driver from the container', function () {
    expect(app(ExecutionDriverContract::class))
        ->toBeInstanceOf(DefaultExecutionDriver::class);
});

it('resolves the singleton execution driver registry from the container', function () {
    $registry = app(ExecutionDriverRegistry::class);

    expect($registry)->toBe(app(ExecutionDriverRegistry::class))
        ->and($registry->resolve('default'))
        ->toBeInstanceOf(DefaultExecutionDriver::class)
        ->and($registry->resolve('settlement_envelope'))
        ->toBeInstanceOf(SettlementEnvelopeExecutionDriver::class)
        ->and($registry->resolve('stored_value'))
        ->toBeInstanceOf(StoredValueExecutionDriver::class);
});

it('resolves the singleton execution pipeline runtime and step registry from the container', function () {
    expect(app(ExecutionPipelineStepRegistry::class))
        ->toBe(app(ExecutionPipelineStepRegistry::class))
        ->and(app(ExecutionPipelineRuntime::class))
        ->toBe(app(ExecutionPipelineRuntime::class));
});

it('resolves the settlement envelope execution gateway seam from the container', function () {
    expect(app(SettlementEnvelopeExecutionGateway::class))
        ->toBeInstanceOf(NullSettlementEnvelopeExecutionGateway::class);
});

it('resolves the stored value execution gateway seam from the container', function () {
    expect(app(StoredValueExecutionGateway::class))
        ->toBeInstanceOf(NullStoredValueExecutionGateway::class);
});

it('boots without provider packages installed', function () {
    expect(app(PayoutProvider::class))->toBeInstanceOf(PayoutProvider::class);
});

it('boots with only emi-core contracts available', function () {
    expect(interface_exists(PayoutProvider::class))->toBeTrue();
    expect(class_exists(PayoutRequestData::class))->toBeTrue();
    expect(class_exists(PayoutResultData::class))->toBeTrue();
});
