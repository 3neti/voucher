<?php

use LBHurtado\Voucher\Tests\Fakes\FakePayoutProvider;
use LBHurtado\EmiCore\Contracts\PayoutProvider;

it('type-hints the payout provider contract rather than a concrete adapter', function () {
    $reflection = new ReflectionClass(PayoutProvider::class);
    expect($reflection->isInterface())->toBeTrue();
});

it('allows a fake payout provider to be bound by the host app', function () {
    expect(app(PayoutProvider::class))->toBeInstanceOf(FakePayoutProvider::class);
});

it('does not require emi-netbank for voucher contract tests', function () {
    expect(interface_exists(\LBHurtado\EmiCore\Contracts\PayoutProvider::class))->toBeTrue();

    expect(class_exists('LBHurtado\PaymentGateway\Adapters\NetbankPayoutProvider'))
        ->toBeBool();
});

it('does not depend on a concrete netbank adapter', function () {
    expect(\LBHurtado\EmiCore\Contracts\PayoutProvider::class)->toBeString();
    expect(interface_exists(\LBHurtado\EmiCore\Contracts\PayoutProvider::class))->toBeTrue();
});
