<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedeemerAndCash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('uses the configured redemption pipeline in the intended order', function () {
    expect(config('voucher-pipeline.post-redemption'))->toBe([
        ValidateRedeemerAndCash::class,
        DisburseCash::class,
    ]);
});

it('redeems a valid voucher successfully', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    $result = RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->metadata)->toHaveKey('disbursement');
});

it('rejects an already redeemed voucher', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue();
    expect(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();
});

it('rejects an expired voucher', function () {
    $voucher = issueVoucher();
    $voucher->expires_at = now()->subMinute();
    $voucher->save();

    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();
});

it('keeps redemption successful but records a pending disbursement on downstream provider failure', function () {
    fakePayoutProvider()->willReturnFailedResult(transactionId: 'TXN-FAIL-001');

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    $result = RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->metadata['disbursement']['status'])->toBe('pending')
        ->and($voucher->metadata['disbursement']['requires_reconciliation'])->toBeTrue();
});
