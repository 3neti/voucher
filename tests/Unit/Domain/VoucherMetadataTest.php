<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('persists arbitrary metadata without mutation', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'metadata' => [
            'custom_flag' => 'keep-me',
            'audit_tag' => 'A-001',
            'created_at' => now()->toIso8601String(),
            'issued_at' => now()->toIso8601String(),
        ],
    ]));

    expect($voucher->metadata)->toHaveKey('instructions')
        ->and($voucher->metadata['instructions'])->toHaveKey('metadata');
});

it('round-trips metadata through serialization', function () {
    $voucher = issueVoucher();
    $reloaded = $voucher->fresh();

    expect($reloaded->metadata)->toBeArray()
        ->and(json_decode(json_encode($reloaded->metadata), true))->toBeArray();
});

it('merges metadata without overwriting unrelated keys', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $before = $voucher->metadata;

    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata)->toHaveKey('instructions')
        ->and($voucher->metadata)->toHaveKey('disbursement')
        ->and($voucher->metadata['instructions'])->toMatchArray($before['instructions']);
});

it('stores disbursement metadata after payout', function () {
    fakePayoutProvider()->willReturnSuccessfulResult(
        transactionId: 'TXN-META-900',
        uuid: 'uuid-meta-900',
        provider: 'fake-gateway'
    );

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata)->toHaveKey('disbursement')
        ->and($voucher->metadata['disbursement']['transaction_id'])->toBe('TXN-META-900')
        ->and($voucher->metadata['disbursement']['transaction_uuid'])->toBe('uuid-meta-900')
        ->and($voucher->metadata['disbursement']['gateway'])->toBe('fake-gateway');
});

it('preserves audit keys across reloads', function () {
    $voucher = issueVoucher();
    $reloaded = $voucher->fresh();

    expect($reloaded->metadata)->toHaveKey('instructions')
        ->and($reloaded->metadata['instructions'])->toHaveKey('cash')
        ->and($reloaded->metadata['instructions'])->toHaveKey('feedback');
});
