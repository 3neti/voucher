<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('treats provider timeout as a controlled failure', function () {
    fakePayoutProvider()->willThrow(new RuntimeException('Simulated provider timeout'));

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    $result = RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->metadata['disbursement']['status'])->toBe('pending')
        ->and($voucher->metadata['disbursement']['error'])->toContain('Simulated provider timeout')
        ->and($voucher->metadata['disbursement']['requires_reconciliation'])->toBeTrue();
});

it('does not double-spend when the redemption is retried after timeout', function () {
    fakePayoutProvider()->willThrow(new RuntimeException('Simulated provider timeout'));

    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00));
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue()
        ->and(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();

    $voucher->refresh();

    expect($voucher->getRedeemedTotal())->toBe(0.0)
        ->and($voucher->getRemainingBalance())->toBe(100.0);
});

it('preserves retry-relevant metadata after timeout', function () {
    fakePayoutProvider()->willThrow(new RuntimeException('Simulated provider timeout'));

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata['disbursement'])->toHaveKey('transaction_id')
        ->and($voucher->metadata['disbursement'])->toHaveKey('recipient_identifier')
        ->and($voucher->metadata['disbursement'])->toHaveKey('settlement_rail')
        ->and($voucher->metadata['disbursement'])->toHaveKey('error')
        ->and($voucher->metadata['disbursement']['requires_reconciliation'])->toBeTrue();
});
