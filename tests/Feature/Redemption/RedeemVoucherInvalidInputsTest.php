<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('rejects invalid bank details', function () {
    $this->markTestSkipped('The current voucher package does not yet enforce bank-code/account validation at the voucher layer. That behavior belongs either in a dedicated validator or an external bank registry gate.');
});

it('rejects invalid mobile details when required', function () {
    $this->markTestSkipped('Mobile validation rules are not yet enforced by the current redemption pipeline.');
});

it('does not disburse on validation failure', function () {
    $this->markTestSkipped('Enable this once invalid-input validation is implemented in the redemption pipeline.');
});

it('does not withdraw on validation failure', function () {
    $this->markTestSkipped('Enable this once invalid-input validation is implemented in the redemption pipeline.');
});
