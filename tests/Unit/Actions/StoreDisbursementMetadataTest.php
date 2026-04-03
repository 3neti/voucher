<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('stores transaction_id from payout result data', function () {
    fakePayoutProvider()->willReturnSuccessfulResult(transactionId: 'TXN-META-001');

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata['disbursement']['transaction_id'])->toBe('TXN-META-001');
});

it('stores uuid from payout result data', function () {
    fakePayoutProvider()->willReturnSuccessfulResult(uuid: 'uuid-meta-001');

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata['disbursement']['transaction_uuid'])->toBe('uuid-meta-001');
});

it('stores status from payout result data', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata['disbursement']['status'])->toBe('completed');
});

it('stores provider from payout result data', function () {
    fakePayoutProvider()->willReturnSuccessfulResult(provider: 'fake-gateway');

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata['disbursement']['gateway'])->toBe('fake-gateway');
});

it('stores normalized bank metadata alongside payout result data', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    RedeemVoucher::run(
        makeContactForRedemption([
            'bank_code' => 'GCASH',
            'account_number' => '09171234567',
        ]),
        $voucher->code
    );
    $voucher->refresh();

    expect($voucher->metadata['disbursement']['metadata'])->toHaveKey('bank_code')
        ->and($voucher->metadata['disbursement']['metadata'])->toHaveKey('bank_name')
        ->and($voucher->metadata['disbursement']['metadata'])->toHaveKey('rail')
        ->and($voucher->metadata['disbursement']['metadata'])->toHaveKey('is_emi');
});

it('preserves previously stored voucher metadata when appending disbursement details', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'metadata' => [
            'custom_flag' => 'keep-me',
            'created_at' => now()->toIso8601String(),
            'issued_at' => now()->toIso8601String(),
        ],
    ]));

    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata)->toHaveKey('instructions')
        ->and($voucher->metadata)->toHaveKey('disbursement');
});
