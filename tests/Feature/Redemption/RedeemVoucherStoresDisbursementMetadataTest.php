<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('stores transaction_id uuid status provider and metadata from the payout result', function () {
    fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'TXN-DISB-001',
        uuid: 'uuid-disb-001',
        provider: 'fake-gateway'
    );

    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00, settlementRail: 'INSTAPAY'));
    $contact = makeContactForRedemption([
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ]);

    RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata)->toHaveKey('disbursement')
        ->and($voucher->metadata['disbursement']['transaction_id'])->toBe('TXN-DISB-001')
        ->and($voucher->metadata['disbursement']['transaction_uuid'])->toBe('uuid-disb-001')
        ->and($voucher->metadata['disbursement']['status'])->toBe('completed')
        ->and($voucher->metadata['disbursement']['gateway'])->toBe('fake-gateway')
        ->and($voucher->metadata['disbursement']['metadata']['bank_code'])->toBe('GCASH')
        ->and($voucher->metadata['disbursement']['metadata']['rail'])->toBe('INSTAPAY');
});

it('preserves the stored disbursement metadata after reload', function () {
    fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'TXN-DISB-002',
        uuid: 'uuid-disb-002',
        provider: 'fake-gateway'
    );

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);

    $reloaded = $voucher->fresh();

    expect($reloaded->metadata)->toHaveKey('disbursement')
        ->and($reloaded->metadata['disbursement']['transaction_id'])->toBe('TXN-DISB-002')
        ->and($reloaded->metadata['disbursement']['transaction_uuid'])->toBe('uuid-disb-002')
        ->and($reloaded->metadata['disbursement'])->toHaveKey('metadata');
});
