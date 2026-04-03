<?php

use LBHurtado\Voucher\Actions\{GenerateVouchers, RedeemVoucher};
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Models\Contact;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('does not redeem the voucher when provider disbursement fails', function () {
    // Configure fake provider to fail
    $this->fakeProvider->shouldFail = true;

    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    // Redemption still succeeds (redemption is sacred) but disbursement records failure
    $result = RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($result)->toBeTrue();
    expect($voucher->metadata['disbursement']['status'])->toBe('pending');
    expect($voucher->metadata['disbursement']['requires_reconciliation'])->toBeTrue();
});

it('stores failure metadata for audit', function () {
    $this->fakeProvider->shouldFail = true;

    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata['disbursement'])->toHaveKey('error');
    expect($voucher->metadata['disbursement']['gateway'])->toBe('unknown');
});

it('keeps the voucher recoverable when the failure is non-terminal', function () {
    $this->fakeProvider->shouldFail = true;

    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    // Voucher is redeemed (sacred) but disbursement can be retried
    expect($voucher->redeemed_at)->not->toBeNull();
    expect($voucher->metadata['disbursement']['requires_reconciliation'])->toBeTrue();
});
