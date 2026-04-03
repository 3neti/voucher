<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('enters an effectively partial condition after the first partial redemption', function () {
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

    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $voucher->code, [
        'inputs' => [
            'requested_amount' => 100.00,
        ],
    ]);

    $voucher->refresh();

    expect($voucher->isDivisible())->toBeTrue()
        ->and($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->getRedeemedTotal())->toBe(100.0)
        ->and($voucher->getRemainingBalance())->toBe(200.0)
        ->and($voucher->getRemainingBalance())->toBeGreaterThan(0);
});

it('reduces remaining amount exactly after a partial redemption', function () {
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
            'requested_amount' => 125.50,
        ],
    ]);

    $voucher->refresh();

    expect($voucher->getRedeemedTotal())->toBe(125.5)
        ->and($voucher->getRemainingBalance())->toBe(174.5);
});

it('stays withdrawable while remaining amount is positive', function () {
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
            'requested_amount' => 100.00,
        ],
    ]);

    $voucher->refresh();

    expect($voucher->canRedeem())->toBeFalse()
        ->and($voucher->getRemainingBalance())->toBeGreaterThan(0);
});

it('becomes fully exhausted when remaining amount reaches zero', function () {
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
            'requested_amount' => 300.00,
        ],
    ]);

    $voucher->refresh();

    expect($voucher->getRedeemedTotal())->toBe(300.0)
        ->and($voucher->getRemainingBalance())->toBe(0.0);
});

it('rejects redemptions larger than the remaining balance by skipping disbursement', function () {
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
            'requested_amount' => 500.00,
        ],
    ]);

    $voucher->refresh();

    expect(test()->fakePayoutProvider()->disburseCallCount)->toBe(0)
        ->and($voucher->getRedeemedTotal())->toBe(0.0)
        ->and($voucher->getRemainingBalance())->toBe(300.0);
});
