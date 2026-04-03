<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('redeems only the requested amount for an open divisible voucher', function () {
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

    $result = RedeemVoucher::run($contact, $voucher->code, [
        'inputs' => [
            'requested_amount' => 100.00,
        ],
    ]);

    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->getRedeemedTotal())->toBe(100.0)
        ->and($voucher->getRemainingBalance())->toBe(200.0);
});

it('stores partial redemption inputs on the redeemer metadata', function () {
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
    $redeemer = $voucher->redeemers()->first();

    expect($redeemer)->not->toBeNull()
        ->and($redeemer->metadata)->toHaveKey('redemption')
        ->and($redeemer->metadata['redemption'])->toHaveKey('inputs')
        ->and((float) $redeemer->metadata['redemption']['inputs']['requested_amount'])->toBe(100.0);
});

it('creates a payout request only for the requested amount', function () {
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

    $request = test()->fakePayoutProvider()->lastRequest;

    expect($request)->not->toBeNull()
        ->and($request->amount)->toBe(100.0)
        ->and($request->settlement_rail)->toBe('INSTAPAY');
});

it('leaves the voucher with remaining balance after partial redemption', function () {
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

    expect($voucher->getRemainingBalance())->toBe(200.0)
        ->and($voucher->getRemainingBalance())->toBeGreaterThan(0)
        ->and($voucher->canRedeem())->toBeFalse();
});
