<?php

use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Contracts\GeneratesVouchers;
use LBHurtado\Voucher\Contracts\RedeemsVouchers;

it('binds voucher generation contract to the existing generation action', function () {
    expect(interface_exists(GeneratesVouchers::class))->toBeTrue()
        ->and(app(GeneratesVouchers::class))->toBeInstanceOf(GenerateVouchers::class)
        ->and(app(GeneratesVouchers::class))->toBeInstanceOf(GeneratesVouchers::class);
});

it('binds voucher redemption contract to the existing redemption action', function () {
    expect(interface_exists(RedeemsVouchers::class))->toBeTrue()
        ->and(app(RedeemsVouchers::class))->toBeInstanceOf(RedeemVoucher::class)
        ->and(app(RedeemsVouchers::class))->toBeInstanceOf(RedeemsVouchers::class);
});
