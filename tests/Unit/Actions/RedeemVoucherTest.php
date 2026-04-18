<?php

use LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedeemerAndCash;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('uses the configured redemption pipeline in the intended order', function () {
    expect(config('voucher-pipeline.post-redemption'))->toBe([
        ValidateRedeemerAndCash::class,
        ValidateRedemptionContract::class,
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

it('returns false when the voucher has already been redeemed', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();
});

it('returns false when the voucher is expired', function () {
    $voucher = issueVoucher();
    $voucher->expires_at = now()->subMinute();
    $voucher->save();

    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();
});

it('returns false when the voucher has not started yet', function () {
    $voucher = issueVoucher();
    $voucher->starts_at = now()->addMinute();
    $voucher->save();

    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();
});

it('returns false when the voucher code does not exist', function () {
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, 'NOT-A-REAL-CODE'))->toBeFalse();
});

it('keeps redemption successful but records a pending disbursement on downstream provider failure', function () {
    fakePayoutProvider()->willReturnFailedResult(transactionId: 'TXN-FAIL-001');

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    $result = RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->metadata)->toHaveKey('disbursement')
        ->and($voucher->metadata['disbursement']['status'])->toBe('pending')
        ->and($voucher->metadata['disbursement']['requires_reconciliation'])->toBeTrue();
});

it('passes redemption metadata to the voucher facade', function () {
    $contact = makeContactForRedemption();
    $code = 'TEST-CODE';

    $meta = [
        'ip' => '127.0.0.1',
        'channel' => 'sms',
    ];

    Vouchers::shouldReceive('redeem')
        ->once()
        ->with(
            $code,
            $contact,
            ['redemption' => $meta]
        )
        ->andReturnTrue();

    expect(RedeemVoucher::run($contact, $code, $meta))->toBeTrue();
});

it('records disbursement metadata after successful redemption', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata)->toHaveKey('disbursement');
});

it('rethrows unexpected exceptions', function () {
    $contact = makeContactForRedemption();

    Vouchers::shouldReceive('redeem')
        ->once()
        ->andThrow(new RuntimeException('Unexpected failure'));

    expect(fn () => RedeemVoucher::run($contact, 'ANY-CODE'))
        ->toThrow(RuntimeException::class, 'Unexpected failure');
});