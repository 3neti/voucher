+<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('keeps the voucher redeemed when provider disbursement fails because redemption is sacred', function () {
    fakePayoutProvider()->willReturnFailedResult(transactionId: 'TXN-FAIL-001');

    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00));
    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->metadata['disbursement']['status'])->toBe('pending');
});

it('does not withdraw cash when provider disbursement fails', function () {
    fakePayoutProvider()->willReturnFailedResult(transactionId: 'TXN-FAIL-002');

    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00));
    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($voucher->getRedeemedTotal())->toBe(0.0)
        ->and($voucher->getRemainingBalance())->toBe(100.0);
});

it('stores failure metadata for audit and retry', function () {
    fakePayoutProvider()->willReturnFailedResult(transactionId: 'TXN-FAIL-003');

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata['disbursement']['error'])->not->toBeEmpty()
        ->and($voucher->metadata['disbursement']['requires_reconciliation'])->toBeTrue()
        ->and($voucher->metadata['disbursement']['transaction_id'])->not->toBeEmpty();
});

it('normalizes provider failure into pending reconciliation metadata', function () {
    fakePayoutProvider()->willReturnFailedResult(transactionId: 'TXN-FAIL-004');

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata['disbursement']['status'])->toBe('pending')
        ->and($voucher->metadata['disbursement']['gateway'])->toBe('unknown');
});

it('treats provider exceptions as controlled failures', function () {
    fakePayoutProvider()->willThrow(new RuntimeException('Simulated provider timeout'));

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->metadata['disbursement']['status'])->toBe('pending')
        ->and($voucher->metadata['disbursement']['error'])->toContain('Simulated provider timeout')
        ->and($voucher->metadata['disbursement']['requires_reconciliation'])->toBeTrue();
});
