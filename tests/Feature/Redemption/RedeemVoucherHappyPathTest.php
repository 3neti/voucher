<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Events\DisbursementRequested;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('validates persists disburses withdraws stores metadata and sends feedbacks successfully', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    $result = RedeemVoucher::run($contact, $voucher->code);

    expect($result)->toBeTrue();
});

it('marks the voucher redeemed on a successful full redemption', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($voucher->redeemed_at)->not->toBeNull();
});

it('stores payout result data on the voucher', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata)->toHaveKey('disbursement');
    expect($voucher->metadata['disbursement'])->toHaveKey('transaction_id');
    expect($voucher->metadata['disbursement'])->toHaveKey('status');
});

it('dispatches disbursement requested during the flow', function () {
    Event::fake([DisbursementRequested::class]);

    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    RedeemVoucher::run($contact, $voucher->code);

    Event::assertDispatched(DisbursementRequested::class);
});
