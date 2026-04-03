<?php

use LBHurtado\Voucher\Actions\{GenerateVouchers, RedeemVoucher};
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\Contact\Models\Contact;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('builds payout request data from voucher domain values', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    RedeemVoucher::run($contact, $voucher->code);

    $request = $this->fakeProvider->lastRequest;
    expect($request)->toBeInstanceOf(PayoutRequestData::class);
});

it('maps reference into payout request data', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    RedeemVoucher::run($contact, $voucher->code);

    expect($this->fakeProvider->lastRequest->reference)->toContain($voucher->code);
});

it('maps amount into payout request data', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    RedeemVoucher::run($contact, $voucher->code);

    expect($this->fakeProvider->lastRequest->amount)->toBeGreaterThan(0);
});

it('maps bank_code into payout request data', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GXCHPHM2XXX:09171234567']);

    RedeemVoucher::run($contact, $voucher->code);

    expect($this->fakeProvider->lastRequest->bank_code)->toBe('GXCHPHM2XXX');
});

it('maps settlement_rail into payout request data', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    RedeemVoucher::run($contact, $voucher->code);

    expect($this->fakeProvider->lastRequest->settlement_rail)->toBeIn(['INSTAPAY', 'PESONET']);
});

it('produces a provider-neutral payout request payload', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    RedeemVoucher::run($contact, $voucher->code);

    $request = $this->fakeProvider->lastRequest;
    expect($request)->toBeInstanceOf(PayoutRequestData::class);
    expect($request->reference)->not->toBeEmpty();
    expect($request->amount)->toBeNumeric();
    expect($request->account_number)->not->toBeEmpty();
    expect($request->bank_code)->not->toBeEmpty();
    expect($request->settlement_rail)->not->toBeEmpty();
});
