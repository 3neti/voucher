<?php

use LBHurtado\Voucher\Actions\{GenerateVouchers, RedeemVoucher};
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Models\Contact;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('blocks duplicate redemption of the same voucher', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $contact = Contact::factory()->create(['bank_account' => 'GCASH:09171234567']);

    $first = RedeemVoucher::run($contact, $voucher->code);
    $second = RedeemVoucher::run($contact, $voucher->code);

    expect($first)->toBeTrue();
    expect($second)->toBeFalse();
});
