<?php

use LBHurtado\Voucher\Tests\Fakes\FakePayoutProvider;
use LBHurtado\EmiCore\Contracts\BankRegistryContract;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Support\NullBankRegistry;

it('boots without provider packages installed', function () {
    expect(true)->toBeTrue();
});

it('resolves a safe bank registry fallback through emi-core', function () {
    $registry = app(BankRegistryContract::class);

    expect($registry)->toBeInstanceOf(BankRegistryContract::class)
        ->and($registry)->toBeInstanceOf(NullBankRegistry::class);
});

it('boots without emi-netbank installed', function () {
    // The package boots with FakePayoutProvider — no real provider needed
    expect(app(PayoutProvider::class))->toBeInstanceOf(FakePayoutProvider::class);
});

it('boots without emi-paynamics-constellation installed', function () {
    // No paynamics classes are needed for voucher to boot
    expect(app(PayoutProvider::class))->toBeInstanceOf(FakePayoutProvider::class);
});

it('works with a host-bound fake payout provider only', function () {
    $provider = app(PayoutProvider::class);
    expect($provider)->toBeInstanceOf(PayoutProvider::class);
    expect($provider)->toBeInstanceOf(FakePayoutProvider::class);
});
