<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('never allows redeemed total to exceed face amount', function () {
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
            'requested_amount' => 999.00,
        ],
    ]);

    $voucher->refresh();

    expect($voucher->getRedeemedTotal())->toBeLessThanOrEqual(300.0)
        ->and($voucher->getRemainingBalance())->toBe(300.0);
});

it('never allows remaining amount to go below zero', function () {
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
            'requested_amount' => 999.00,
        ],
    ]);

    $voucher->refresh();

    expect($voucher->getRemainingBalance())->toBeGreaterThanOrEqual(0.0);
});

it('rejects zero-amount partial redemption', function () {
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
            'requested_amount' => 0,
        ],
    ]);

    $voucher->refresh();

    expect(test()->fakePayoutProvider()->disburseCallCount)->toBe(0)
        ->and($voucher->getRedeemedTotal())->toBe(0.0)
        ->and($voucher->getRemainingBalance())->toBe(300.0);
});

it('preserves decimal precision for voucher balances', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.05,
        settlementRail: 'INSTAPAY',
        overrides: [
            'cash' => [
                'slice_mode' => 'fixed',
                'slices' => 3,
            ],
        ],
    ));

    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->getRedeemedTotal())->toEqualWithDelta(33.35, 0.01)
        ->and($voucher->getRemainingBalance())->toEqualWithDelta(66.70, 0.01);
});

it('keeps original amount immutable after redemption activity', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.05,
        settlementRail: 'INSTAPAY',
        overrides: [
            'cash' => [
                'slice_mode' => 'fixed',
                'slices' => 3,
            ],
        ],
    ));

    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->instructions->cash->amount)->toBe(100.05)
        ->and($voucher->getRedeemedTotal())->toBeLessThan($voucher->instructions->cash->amount);
});
