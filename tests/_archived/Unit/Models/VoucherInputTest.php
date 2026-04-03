<?php

use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    // nothing to seed: the Vouchers facade will handle creation
});

it('returns the real code column via __get', function () {
    $voucher = Vouchers::create();       // creates & persists a Voucher
    $code = $voucher->code;

    // Even if somebody later adds an input named "code", $voucher->code stays the real column:
    $voucher->inputs()->create(['name' => 'code', 'value' => 'SHOULD_NOT_OVERRIDE']);

    expect($voucher->code)->toBe($code);
});

it('allows reading other inputs via magic __get only when not a real attribute', function () {
    $voucher = Vouchers::create();

    // There's no "mobile" attribute on vouchers, so magic should pick it up from inputs:
    $voucher->inputs()->create(['name' => 'mobile', 'value' => '09171234567']);

    expect($voucher->mobile)->toBe('09171234567');
});

it('persists inputs when you assign via magic __set', function () {
    $voucher = Vouchers::create();

    // Assign via magic setter; "signature" isn't a real voucher column so lands in inputs
    $voucher->signature = 'signature_block';
    $voucher->save();

    $this->assertDatabaseHas('inputs', [
        'model_type' => Voucher::class,
        'model_id' => $voucher->getKey(),
        'name' => 'signature',
        'value' => 'signature_block',
    ]);

    expect($voucher->signature)->toBe('signature_block');
});

it('setting a real attribute via magic __set still updates the attribute', function () {
    $voucher = Vouchers::create();
    $oldCode = $voucher->code;

    // code is a real column, so __set should update the DB column, not create an input:
    $voucher->code = 'NEWCODE123';
    $voucher->save();

    $this->assertDatabaseMissing('inputs', [
        'model_type' => Voucher::class,
        'model_id' => $voucher->getKey(),
        'name' => 'code',
    ]);

    expect($voucher->fresh()->code)->toBe('NEWCODE123');
});

it('input() helper returns the raw input value regardless of column collisions', function () {
    $voucher = Vouchers::create();

    // Force‐set an input named "code" (to collide)
    $voucher->forceSetInput('code', 'SHOULD_NOT_DISPLAY_AS_CODE');

    // input('code') always returns the inputs‐table value:
    expect($voucher->input('code'))->toBe('SHOULD_NOT_DISPLAY_AS_CODE');

    // But magic $voucher->code is still the real column:
    expect($voucher->code)->not()->toBe('SHOULD_NOT_DISPLAY_AS_CODE');
});
