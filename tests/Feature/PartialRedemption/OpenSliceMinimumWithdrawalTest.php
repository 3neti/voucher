<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('does not disburse when requested amount is below the configured minimum withdrawal', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 300.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'cash' => [
                'slice_mode' => 'open',
                'min_withdrawal' => 50,
            ],
        ],
    ));

    RedeemVoucher::run(makeContactForRedemption(), $voucher->code, [
        'inputs' => [
            'requested_amount' => 25.00,
        ],
    ]);

    expect(test()->fakePayoutProvider()->disburseCallCount)->toBe(0)
        ->and(test()->fakePayoutProvider()->lastRequest)->toBeNull();
});

it('keeps balances unchanged when requested amount is below the configured minimum withdrawal', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 300.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'cash' => [
                'slice_mode' => 'open',
                'min_withdrawal' => 50,
            ],
        ],
    ));

    $result = RedeemVoucher::run(makeContactForRedemption(), $voucher->code, [
        'inputs' => [
            'requested_amount' => 25.00,
        ],
    ]);

    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and($voucher->getRedeemedTotal())->toBe(0.0)
        ->and($voucher->getRemainingBalance())->toBe(300.0);
});

it('accepts redemption when requested amount matches the configured minimum withdrawal', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 300.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'cash' => [
                'slice_mode' => 'open',
                'min_withdrawal' => 50,
            ],
        ],
    ));

    $result = RedeemVoucher::run(makeContactForRedemption(), $voucher->code, [
        'inputs' => [
            'requested_amount' => 50.00,
        ],
    ]);

    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and(test()->fakePayoutProvider()->disburseCallCount)->toBe(1)
        ->and($voucher->getRedeemedTotal())->toBe(50.0)
        ->and($voucher->getRemainingBalance())->toBe(250.0);
});
