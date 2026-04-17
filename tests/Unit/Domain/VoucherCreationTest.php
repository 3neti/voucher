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
        ->and($voucher->starts_at ?? null)->toBeNull()
        ->and($voucher->expires_at)->not->toBeNull()
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

it('persists starts_at on generated voucher', function () {
    $startsAt = now()->addHours(2)->startOfSecond();

    $instructions = validVoucherInstructions(overrides: [
        'starts_at' => $startsAt->toIso8601String(),
    ]);

    $voucher = issueVoucher($instructions);
    $voucher->refresh();

    expect($voucher->starts_at)->not->toBeNull()
        ->and($voucher->starts_at->equalTo($startsAt))->toBeTrue()
        ->and($voucher->expires_at)->not->toBeNull()
        ->and($voucher->expires_at->greaterThan($voucher->starts_at))->toBeTrue()
        ->and($voucher->state)->toBe(VoucherState::ACTIVE);
});

it('persists expires_at on generated voucher', function () {
    $expiresAt = now()->addDay()->startOfSecond();

    $instructions = validVoucherInstructions(overrides: [
        'expires_at' => $expiresAt->toIso8601String(),
    ]);

    $voucher = issueVoucher($instructions);
    $voucher->refresh();

    expect($voucher->expires_at)->not->toBeNull()
        ->and($voucher->expires_at->equalTo($expiresAt))->toBeTrue()
        ->and($voucher->starts_at ?? null)->toBeNull()
        ->and($voucher->state)->toBe(VoucherState::ACTIVE);
});

it('persists starts_at and expires_at together on generated voucher', function () {
    $startsAt = now()->addHour()->startOfSecond();
    $expiresAt = now()->addDay()->startOfSecond();

    $instructions = validVoucherInstructions(overrides: [
        'starts_at' => $startsAt->toIso8601String(),
        'expires_at' => $expiresAt->toIso8601String(),
    ]);

    $voucher = issueVoucher($instructions);
    $voucher->refresh();

    expect($voucher->starts_at)->not->toBeNull()
        ->and($voucher->expires_at)->not->toBeNull()
        ->and($voucher->starts_at->equalTo($startsAt))->toBeTrue()
        ->and($voucher->expires_at->equalTo($expiresAt))->toBeTrue()
        ->and($voucher->expires_at->greaterThan($voucher->starts_at))->toBeTrue();
});

it('computes expires_at from ttl when explicit expires_at is absent', function () {
    $now = now()->startOfSecond();
    \Illuminate\Support\Facades\Date::setTestNow($now);

    $instructions = validVoucherInstructions(overrides: [
        'ttl' => 'PT2H',
    ]);

    $voucher = issueVoucher($instructions);
    $voucher->refresh();

    expect($voucher->expires_at)->not->toBeNull()
        ->and($voucher->expires_at->equalTo($now->copy()->addHours(2)))->toBeTrue()
        ->and($voucher->starts_at ?? null)->toBeNull();
});

it('prefers explicit expires_at over ttl', function () {
    $expiresAt = now()->addDays(2)->startOfSecond();

    $instructions = validVoucherInstructions(overrides: [
        'ttl' => 'PT2H',
        'expires_at' => $expiresAt->toIso8601String(),
    ]);

    $voucher = issueVoucher($instructions);
    $voucher->refresh();

    expect($voucher->expires_at)->not->toBeNull()
        ->and($voucher->expires_at->equalTo($expiresAt))->toBeTrue();
});

it('applies the default 12 hour expiry when no ttl or expires_at is provided', function () {
    $now = now()->startOfSecond();
    \Illuminate\Support\Facades\Date::setTestNow($now);

    $voucher = issueVoucher();
    $voucher->refresh();

    expect($voucher->expires_at)->not->toBeNull()
        ->and($voucher->expires_at->equalTo($now->copy()->addHours(12)))->toBeTrue()
        ->and($voucher->starts_at ?? null)->toBeNull();
});