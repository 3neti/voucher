<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Enums\VoucherState;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('allows the active lifecycle state to redeem a fresh redeemable voucher', function () {
    $voucher = issueVoucher();

    expect($voucher->state)->toBe(VoucherState::ACTIVE)
        ->and($voucher->display_status)->toBe('active')
        ->and($voucher->canRedeem())->toBeTrue()
        ->and($voucher->canAcceptPayment())->toBeFalse();
});

it('rejects redemption when the voucher is locked cancelled or closed', function () {
    $locked = issueVoucher();
    $locked->update(['state' => VoucherState::LOCKED]);

    $closed = issueVoucher();
    $closed->update(['state' => VoucherState::CLOSED]);

    $cancelled = issueVoucher();
    $cancelled->update(['state' => VoucherState::CANCELLED]);

    expect($locked->fresh()->canRedeem())->toBeFalse()
        ->and($closed->fresh()->canRedeem())->toBeFalse()
        ->and($cancelled->fresh()->canRedeem())->toBeFalse();
});

it('does not allow a redeemed voucher to return to a redeemable condition', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    $voucher->state = VoucherState::ACTIVE;
    $voucher->save();
    $voucher->refresh();

    expect($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->display_status)->toBe('redeemed')
        ->and($voucher->canRedeem())->toBeFalse();
});

it('treats partial redemption as a distinct effective condition when the voucher is divisible', function () {
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

    expect($voucher->isDivisible())->toBeTrue()
        ->and($voucher->getConsumedSlices())->toBe(1)
        ->and($voucher->getRemainingSlices())->toBe(2)
        ->and($voucher->getRemainingBalance())->toBe(200.0)
        ->and($voucher->canWithdraw())->toBeTrue();
});

it('prevents duplicate terminal redemption transitions', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue()
        ->and(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();
});
