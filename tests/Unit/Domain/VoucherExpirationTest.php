<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('treats a non-expired voucher as redeemable', function () {
    $voucher = issueVoucher();
    $voucher->update(['expires_at' => now()->addHour()]);

    expect($voucher->fresh()->isExpired())->toBeFalse()
        ->and($voucher->fresh()->canRedeem())->toBeTrue();
});

it('rejects redemption of an expired voucher', function () {
    $voucher = issueVoucher();
    $voucher->update(['expires_at' => now()->subMinute()]);

    $result = RedeemVoucher::run(makeContactForRedemption(), $voucher->fresh()->code);

    expect($result)->toBeFalse()
        ->and($voucher->fresh()->redeemed_at)->toBeNull();
});

it('does not treat the exact expiration timestamp as expired', function () {
    Carbon::setTestNow('2026-04-02 12:00:00');

    $voucher = issueVoucher();
    $voucher->update(['expires_at' => now()]);

    expect($voucher->fresh()->isExpired())->toBeFalse()
        ->and($voucher->fresh()->canRedeem())->toBeTrue();

    Carbon::setTestNow();
});

it('treats a voucher as expired immediately after the expiration timestamp passes', function () {
    Carbon::setTestNow('2026-04-02 12:00:01');

    $voucher = issueVoucher();
    $voucher->update(['expires_at' => Carbon::parse('2026-04-02 12:00:00')]);

    expect($voucher->fresh()->isExpired())->toBeTrue()
        ->and($voucher->fresh()->canRedeem())->toBeFalse();

    Carbon::setTestNow();
});

it('handles timezone-sensitive expiry consistently', function () {
    Carbon::setTestNow(Carbon::parse('2026-04-02 20:00:00', 'Asia/Manila')->utc());

    $voucher = issueVoucher();
    $voucher->update([
        'expires_at' => Carbon::parse('2026-04-02 19:59:59', 'Asia/Manila')->utc(),
    ]);

    expect($voucher->fresh()->isExpired())->toBeTrue();

    Carbon::setTestNow();
});
