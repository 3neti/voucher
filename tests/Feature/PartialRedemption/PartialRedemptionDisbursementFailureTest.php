<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('marks partial redemption disbursement failure as pending reconciliation', function () {
    fakePayoutProvider()->willReturnFailedResult(transactionId: 'TXN-PARTIAL-FAIL-001');

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

    $result = RedeemVoucher::run(makeContactForRedemption(), $voucher->code, [
        'inputs' => [
            'requested_amount' => 100.00,
        ],
    ]);

    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->metadata['disbursement']['status'])->toBe('pending')
        ->and($voucher->metadata['disbursement']['requires_reconciliation'])->toBeTrue();
});

it('does not consume voucher value when partial redemption disbursement fails', function () {
    fakePayoutProvider()->willReturnFailedResult(transactionId: 'TXN-PARTIAL-FAIL-002');

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

    expect($voucher->getRedeemedTotal())->toBe(0.0)
        ->and($voucher->getRemainingBalance())->toBe(300.0);
});

it('stores retry-relevant disbursement metadata for failed partial redemption', function () {
    fakePayoutProvider()->willReturnFailedResult(transactionId: 'TXN-PARTIAL-FAIL-003');

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

    expect($voucher->metadata)->toHaveKey('disbursement')
        ->and($voucher->metadata['disbursement']['transaction_id'])->not->toBeEmpty()
        ->and($voucher->metadata['disbursement'])->toHaveKey('recipient_identifier')
        ->and($voucher->metadata['disbursement'])->toHaveKey('settlement_rail')
        ->and($voucher->metadata['disbursement'])->toHaveKey('error')
        ->and($voucher->metadata['disbursement']['requires_reconciliation'])->toBeTrue();
});
