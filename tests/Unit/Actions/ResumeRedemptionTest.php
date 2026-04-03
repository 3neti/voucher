<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('preserves redemption context on a pending disbursement that would later need host-app resume handling', function () {
    fakePayoutProvider()->willThrow(new RuntimeException('Simulated timeout'));

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue();

    $voucher->refresh();

    expect($voucher->metadata)->toHaveKey('disbursement')
        ->and($voucher->metadata['disbursement']['status'])->toBe('pending')
        ->and($voucher->metadata['disbursement'])->toHaveKey('recipient_identifier')
        ->and($voucher->metadata['disbursement'])->toHaveKey('settlement_rail')
        ->and($voucher->metadata['disbursement']['requires_reconciliation'])->toBeTrue();
});

it('does not resume a terminally completed redemption through the package redemption entrypoint', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue()
        ->and(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();
});

it('reuses the same voucher identity safely across repeated attempts even without a public resume action', function () {
    fakePayoutProvider()->willThrow(new RuntimeException('Simulated timeout'));

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $voucher->code);
    $first = $voucher->fresh()->metadata['disbursement'] ?? [];

    RedeemVoucher::run($contact, $voucher->code);
    $second = $voucher->fresh()->metadata['disbursement'] ?? [];

    expect($first['recipient_identifier'] ?? null)->toBe($second['recipient_identifier'] ?? null);
});
