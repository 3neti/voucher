<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('uses the canonical voucher reference for payout requests', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);

    $request = test()->fakePayoutProvider()->lastRequest;

    expect($request)->not->toBeNull()
        ->and($request->external_id)->toBe((string) $voucher->id)
        ->and($request->external_code)->toBe($voucher->code)
        ->and($request->reference)->toContain($voucher->code);
});

it('does not mutate external reference fields during retries', function () {
    fakePayoutProvider()->willThrow(new RuntimeException('Simulated timeout'));

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $voucher->code);
    $firstDisbursement = $voucher->fresh()->metadata['disbursement'] ?? [];

    RedeemVoucher::run($contact, $voucher->code);
    $secondDisbursement = $voucher->fresh()->metadata['disbursement'] ?? [];

    expect($firstDisbursement['recipient_identifier'] ?? null)
        ->toBe($secondDisbursement['recipient_identifier'] ?? null);
});

it('preserves traceability identifiers across the redemption lifecycle', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata['disbursement'])->toHaveKey('transaction_id')
        ->and($voucher->metadata['disbursement'])->toHaveKey('recipient_identifier')
        ->and($voucher->metadata['disbursement'])->toHaveKey('settlement_rail');
});
