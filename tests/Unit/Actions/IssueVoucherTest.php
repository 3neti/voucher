<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('creates a voucher record', function () {
    $voucher = issueVoucher();

    expect($voucher->exists)->toBeTrue()
        ->and($voucher->id)->not->toBeNull()
        ->and($voucher->code)->toBeString()->not->toBeEmpty();
});

it('sets initial redemption state amount and remaining balance', function () {
    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00));

    expect($voucher->redeemed_at)->toBeNull()
        ->and($voucher->canRedeem())->toBeTrue()
        ->and($voucher->getRemainingBalance())->toBe(100.0)
        ->and($voucher->getRedeemedTotal())->toBe(0.0);
});

it('persists metadata during issuance', function () {
    $voucher = issueVoucher();

    expect($voucher->metadata)->toBeArray()
        ->and($voucher->metadata)->toHaveKey('instructions')
        ->and($voucher->metadata['instructions'])->toHaveKey('cash')
        ->and($voucher->metadata['instructions'])->toHaveKey('feedback');
});

it('persists instructions during issuance', function () {
    $voucher = issueVoucher(validVoucherInstructions(amount: 250.00));

    expect($voucher->instructions->cash->amount)->toBe(250.0)
        ->and($voucher->instructions->cash->currency)->toBe('PHP');
});

it('prevents duplicate code collisions across issued vouchers', function () {
    $first = issueVoucher();
    $second = issueVoucher();

    expect($first->code)->not->toBe($second->code);
});

it('returns the issued voucher aggregate as the package voucher model', function () {
    $voucher = issueVoucher();

    expect($voucher)->toBeInstanceOf(Voucher::class);
});
