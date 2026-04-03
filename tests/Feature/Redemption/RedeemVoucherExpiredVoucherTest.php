<?php

use LBHurtado\Voucher\Actions\{GenerateVouchers, RedeemVoucher};
use Illuminate\Foundation\Testing\RefreshDatabase;
//use LBHurtado\Contact\Models\Contact;
//use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('blocks redemption of an expired voucher', function () {
//    $instructions = validInstructions();
//    $voucher = GenerateVouchers::run($instructions)->first();
//
//    // Force expire
//    Voucher::where('id', $voucher->id)->update(['expires_at' => now()->subDay()]);
//
//    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);
//    $result = RedeemVoucher::run($contact, $voucher->fresh()->code);
    $voucher = issueVoucher();
    $voucher->update(['expires_at' => now()->subDay()]);

    $result = RedeemVoucher::run(makeContactForRedemption(), $voucher->fresh()->code);

    expect($result)->toBeFalse();
});

it('does not invoke the payout provider for an expired voucher', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $voucher->update(['expires_at' => now()->subMinute()]);

    RedeemVoucher::run(makeContactForRedemption(), $voucher->fresh()->code);

    expect(test()->fakePayoutProvider()->disburseCallCount)->toBe(0);
});
