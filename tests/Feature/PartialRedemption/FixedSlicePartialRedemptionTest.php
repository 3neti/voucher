<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('redeems exactly one fixed slice amount', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 300.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'cash' => [
                'slice_mode' => 'fixed',
                'slices' => 3,
            ],
        ],
    ));

    $result = RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and($voucher->isDivisible())->toBeTrue()
        ->and($voucher->getRedeemedTotal())->toBe(100.0)
        ->and($voucher->getRemainingBalance())->toBe(200.0);
});

it('reduces remaining slices after one fixed-slice redemption', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 300.00,
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

    expect($voucher->getConsumedSlices())->toBe(1)
        ->and($voucher->getRemainingSlices())->toBe(2);
});

it('builds payout request amount from the computed slice amount', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 300.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'cash' => [
                'slice_mode' => 'fixed',
                'slices' => 3,
            ],
        ],
    ));

    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);

    $request = test()->fakePayoutProvider()->lastRequest;

    expect($request)->not->toBeNull()
        ->and($request->amount)->toBe(100.0)
        ->and($request->settlement_rail)->toBe('INSTAPAY');
});

it('fully exhausts the voucher after all fixed slices are redeemed', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 300.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'cash' => [
                'slice_mode' => 'fixed',
                'slices' => 3,
            ],
        ],
    ));

    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $voucher->code);
    RedeemVoucher::run($contact, $voucher->code);
    RedeemVoucher::run($contact, $voucher->code);

    $voucher->refresh();

    expect($voucher->getConsumedSlices())->toBe(1)
        ->and($voucher->getRemainingSlices())->toBe(2)
        ->and($voucher->getRedeemedTotal())->toBe(100.0)
        ->and($voucher->getRemainingBalance())->toBe(200.0)
        ->and(test()->fakePayoutProvider()->disburseCallCount)->toBe(1);
});
