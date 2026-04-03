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

it('boots without provider packages installed', function () {
    expect(app(PayoutProvider::class))->toBeInstanceOf(PayoutProvider::class);
});

it('boots with only emi-core contracts available', function () {
    expect(interface_exists(PayoutProvider::class))->toBeTrue();
    expect(class_exists(\LBHurtado\EmiCore\Data\PayoutRequestData::class))->toBeTrue();
    expect(class_exists(\LBHurtado\EmiCore\Data\PayoutResultData::class))->toBeTrue();
});
