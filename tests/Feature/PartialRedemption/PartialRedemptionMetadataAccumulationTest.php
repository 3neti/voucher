<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('stores the requested amount in redemption metadata', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

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
            'requested_amount' => 120.00,
        ],
    ]);

    $voucher->refresh();
    $redeemer = $voucher->redeemers()->first();

    expect($redeemer)->not->toBeNull()
        ->and((float) $redeemer->metadata['redemption']['inputs']['requested_amount'])->toBe(120.0);
});

it('preserves voucher metadata when partial redemption occurs', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 300.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'cash' => [
                'slice_mode' => 'open',
                'min_withdrawal' => 50,
            ],
            'metadata' => [
                'custom_flag' => 'keep-me',
                'created_at' => now()->toIso8601String(),
                'issued_at' => now()->toIso8601String(),
            ],
        ],
    ));

    RedeemVoucher::run(makeContactForRedemption(), $voucher->code, [
        'inputs' => [
            'requested_amount' => 120.00,
        ],
    ]);

    $voucher->refresh();

    expect($voucher->metadata)->toHaveKey('instructions')
        ->and($voucher->metadata)->toHaveKey('disbursement')
        ->and($voucher->metadata['instructions'])->toHaveKey('cash');
});

it('stores disbursement metadata for a partial redemption', function () {
    fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'TXN-PARTIAL-001',
        uuid: 'uuid-partial-001',
        provider: 'fake-gateway'
    );

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
            'requested_amount' => 120.00,
        ],
    ]);

    $voucher->refresh();

    expect($voucher->metadata)->toHaveKey('disbursement')
        ->and($voucher->metadata['disbursement']['transaction_id'])->toBe('TXN-PARTIAL-001')
        ->and($voucher->metadata['disbursement']['transaction_uuid'])->toBe('uuid-partial-001')
        ->and($voucher->metadata['disbursement']['status'])->toBe('completed')
        ->and($voucher->metadata['disbursement']['gateway'])->toBe('fake-gateway');
});
