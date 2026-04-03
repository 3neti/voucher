<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('models cancellation safely via voucher state even without a public cancellation action', function () {
    $voucher = issueVoucher();
    $voucher->update(['state' => VoucherState::CANCELLED]);

    expect($voucher->fresh()->state)->toBe(VoucherState::CANCELLED)
        ->and($voucher->fresh()->display_status)->toBe('cancelled')
        ->and($voucher->fresh()->canRedeem())->toBeFalse();
});

it('keeps the voucher unredeemed when cancellation-like state is applied before redemption', function () {
    $voucher = issueVoucher();
    $voucher->update(['state' => VoucherState::CANCELLED]);

    expect($voucher->fresh()->redeemed_at)->toBeNull()
        ->and($voucher->fresh()->getRedeemedTotal())->toBe(0.0)
        ->and($voucher->fresh()->getRemainingBalance())->toBe(100.0);
});

it('can preserve cancellation metadata for audit when the host app writes it', function () {
    $voucher = issueVoucher();
    $metadata = $voucher->metadata;
    $metadata['cancellation'] = [
        'reason' => 'user_aborted',
        'cancelled_by' => 'test-user',
        'cancelled_at' => now()->toIso8601String(),
    ];

    $voucher->forceFill([
        'state' => VoucherState::CANCELLED,
        'metadata' => $metadata,
    ])->save();

    expect($voucher->fresh()->metadata)->toHaveKey('cancellation')
        ->and($voucher->fresh()->metadata['cancellation']['reason'])->toBe('user_aborted');
});

it('does not allow a terminally redeemed voucher to become redeemable again', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue();

    $voucher->refresh();

    expect($voucher->canRedeem())->toBeFalse()
        ->and($voucher->display_status)->toBe('redeemed');
});
