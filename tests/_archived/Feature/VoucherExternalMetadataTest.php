<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\ExternalMetadataData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a voucher for testing using minimal instructions
    $instructions = VoucherInstructionsData::generateFromScratch();
    $vouchers = GenerateVouchers::run($instructions);
    $this->voucher = $vouchers->first();
});

// Helper to create a voucher
function createTestVoucherForMetadata()
{
    $instructions = VoucherInstructionsData::generateFromScratch();

    return GenerateVouchers::run($instructions)->first();
}

test('voucher can get external metadata as DTO', function () {
    $this->voucher->external_metadata = ExternalMetadataData::from([
        'external_id' => 'EXT-001',
        'user_id' => 'USER-456',
    ]);
    $this->voucher->save();

    $this->voucher->refresh();

    expect($this->voucher->external_metadata)->toBeInstanceOf(ExternalMetadataData::class)
        ->and($this->voucher->external_metadata->external_id)->toBe('EXT-001')
        ->and($this->voucher->external_metadata->user_id)->toBe('USER-456');
});

test('voucher can set external metadata from array', function () {
    $this->voucher->external_metadata = [
        'external_id' => 'EXT-002',
        'external_type' => 'game',
        'custom' => ['level' => 10],
    ];
    $this->voucher->save();

    $this->voucher->refresh();

    expect($this->voucher->external_metadata->external_id)->toBe('EXT-002')
        ->and($this->voucher->external_metadata->external_type)->toBe('game')
        ->and($this->voucher->external_metadata->getCustom('level'))->toBe(10);
});

test('voucher external metadata returns null when not set', function () {
    expect($this->voucher->external_metadata)->toBeNull();
});

test('voucher can clear external metadata', function () {
    $this->voucher->external_metadata = ['external_id' => 'EXT-003'];
    $this->voucher->save();

    expect($this->voucher->fresh()->external_metadata)->not->toBeNull();

    $this->voucher->external_metadata = null;
    $this->voucher->save();

    expect($this->voucher->fresh()->external_metadata)->toBeNull();
});

test('can query vouchers by external metadata field', function () {
    // Create vouchers with different external metadata
    $voucher1 = createTestVoucherForMetadata();
    $voucher1->external_metadata = ['user_id' => 'USER-001'];
    $voucher1->save();

    $voucher2 = createTestVoucherForMetadata();
    $voucher2->external_metadata = ['user_id' => 'USER-002'];
    $voucher2->save();

    $voucher3 = createTestVoucherForMetadata();
    $voucher3->external_metadata = ['user_id' => 'USER-001'];
    $voucher3->save();

    $results = Voucher::whereExternal('user_id', 'USER-001')->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id'))->toContain($voucher1->id, $voucher3->id);
});

test('can query vouchers by multiple external metadata values', function () {
    $voucher1 = createTestVoucherForMetadata();
    $voucher1->external_metadata = ['user_id' => 'USER-001'];
    $voucher1->save();

    $voucher2 = createTestVoucherForMetadata();
    $voucher2->external_metadata = ['user_id' => 'USER-002'];
    $voucher2->save();

    $voucher3 = createTestVoucherForMetadata();
    $voucher3->external_metadata = ['user_id' => 'USER-003'];
    $voucher3->save();

    $results = Voucher::whereExternalIn('user_id', ['USER-001', 'USER-003'])->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id'))->toContain($voucher1->id, $voucher3->id);
});

test('existing vouchers without external metadata continue to work', function () {
    // Backward compatibility test
    expect($this->voucher->external_metadata)->toBeNull()
        ->and($this->voucher->code)->not->toBeNull()
        ->and($this->voucher->metadata)->toBeArray();
});

test('external metadata does not interfere with instructions', function () {
    $this->voucher->external_metadata = ['external_id' => 'EXT-004'];
    $this->voucher->save();

    $this->voucher->refresh();

    // Both should be accessible
    expect($this->voucher->external_metadata->external_id)->toBe('EXT-004')
        ->and($this->voucher->instructions)->toBeInstanceOf(\LBHurtado\Voucher\Data\VoucherInstructionsData::class);
});
