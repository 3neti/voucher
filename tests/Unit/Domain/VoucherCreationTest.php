<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('creates a voucher with required attributes', function () {
    $voucher = issueVoucher();

    expect($voucher)->toBeInstanceOf(Voucher::class)
        ->and($voucher->id)->not->toBeNull()
        ->and($voucher->code)->toBeString()->not->toBeEmpty()
        ->and($voucher->voucher_type)->not->toBeNull();
});

it('applies default lifecycle values on creation', function () {
    $voucher = issueVoucher();

    expect($voucher->redeemed_at)->toBeNull()
        ->and($voucher->expired_at ?? null)->toBeNull()
        ->and($voucher->state)->toBe(VoucherState::ACTIVE);
});

it('initializes voucher metadata correctly', function () {
    $voucher = issueVoucher();

    expect($voucher->metadata)->toBeArray()
        ->and($voucher->metadata)->toHaveKey('instructions')
        ->and($voucher->metadata['instructions'])->toHaveKey('cash')
        ->and($voucher->metadata['instructions'])->toHaveKey('feedback');
});

it('initializes voucher instructions correctly', function () {
    $voucher = issueVoucher(validVoucherInstructions(amount: 250.00, settlementRail: 'INSTAPAY'));

    expect($voucher->instructions->cash->amount)->toBe(250.0)
        ->and($voucher->instructions->cash->currency)->toBe('PHP')
        ->and($voucher->instructions->cash->settlement_rail->value)->toBe('INSTAPAY');
});

it('starts in the correct initial state', function () {
    $voucher = issueVoucher();

    expect($voucher->state)->toBe(VoucherState::ACTIVE)
        ->and($voucher->display_status)->toBe('active')
        ->and($voucher->canRedeem())->toBeTrue();
});

it('creates unique voucher codes', function () {
    $first = issueVoucher();
    $second = issueVoucher();

    expect($first->code)->not->toBe($second->code);
});

it('creates unique voucher references when required', function () {
    $first = issueVoucher();
    $second = issueVoucher();

    expect($first->id)->not->toBe($second->id)
        ->and($first->code)->not->toBe($second->code);
});
