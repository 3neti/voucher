<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('does not disburse when requested amount is zero', function () {
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

    expect(test()->fakePayoutProvider()->disburseCallCount)->toBe(0)
        ->and(test()->fakePayoutProvider()->lastRequest)->toBeNull();
});

it('keeps balances unchanged when requested amount is zero', function () {
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
            'requested_amount' => 0,
        ],
    ]);

    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and($voucher->getRedeemedTotal())->toBe(0.0)
        ->and($voucher->getRemainingBalance())->toBe(300.0);
});

it('stores the attempted zero amount in redemption metadata when applicable', function () {
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
    $redeemer = $voucher->redeemers()->first();

    expect($redeemer)->not->toBeNull()
        ->and($redeemer->metadata)->toHaveKey('redemption')
        ->and($redeemer->metadata['redemption'])->toHaveKey('inputs')
        ->and((float) $redeemer->metadata['redemption']['inputs']['requested_amount'])->toBe(0.0);
});
