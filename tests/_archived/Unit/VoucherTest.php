<?php

use Carbon\CarbonInterval;
use Carbon\Exceptions\InvalidFormatException;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

it('can set and get processed attribute correctly', function () {
    // Arrange: Create a voucher using the Vouchers facade
    $instructions = [
        'prefix' => 'TEST',
        'mask' => '***-***',
        'metadata' => ['type' => 'test'],
        'ttl' => CarbonInterval::hours(1),
        'count' => 1,
    ];

    $vouchers = Vouchers::withPrefix($instructions['prefix'])
        ->withMask($instructions['mask'])
        ->withMetadata($instructions['metadata'])
        ->withExpireTimeIn($instructions['ttl'])
        ->create($instructions['count']);

    /** @var Voucher $voucher */
    $voucher = $vouchers->first();

    // Set processed to true
    $voucher->processed = true;
    $voucher->save();

    // Verify the processed column and processed_on timestamp
    expect($voucher->processed)->toBeTrue();
    expect($voucher->processed_on)->toBeInstanceOf(\DateTime::class);
    expect($voucher->processed_on->format('Y-m-d H:i:s'))->toBe((string) now()->format('Y-m-d H:i:s'));

    // Mark voucher as unprocessed
    $voucher->processed = false;
    $voucher->save();

    // Verify the processed column and processed_on is null
    expect($voucher->processed)->toBeFalse();
    expect($voucher->processed_on)->toBeNull();
});

it('raises an InvalidFormatException for invalid processed_on values', function () {
    // Arrange: Create a voucher using the Vouchers facade
    $instructions = [
        'prefix' => 'TEST',
        'mask' => '***-***',
        'metadata' => ['type' => 'test'],
        'ttl' => CarbonInterval::hours(1),
        'count' => 1,
    ];

    $vouchers = Vouchers::withPrefix($instructions['prefix'])
        ->withMask($instructions['mask'])
        ->withMetadata($instructions['metadata'])
        ->withExpireTimeIn($instructions['ttl'])
        ->create($instructions['count']);

    /** @var Voucher $voucher */
    $voucher = $vouchers->first();

    // Act & Assert: Ensure InvalidFormatException is raised
    $voucher->processed_on = 'invalid-date';
    $voucher->save();
})->throws(InvalidFormatException::class);

it('handles valid processed_on values correctly', function () {
    // Arrange: Create a voucher using the Vouchers facade
    $instructions = [
        'prefix' => 'TEST',
        'mask' => '***-***',
        'metadata' => ['type' => 'test'],
        'ttl' => CarbonInterval::hours(1),
        'count' => 1,
    ];

    $vouchers = Vouchers::withPrefix($instructions['prefix'])
        ->withMask($instructions['mask'])
        ->withMetadata($instructions['metadata'])
        ->withExpireTimeIn($instructions['ttl'])
        ->create($instructions['count']);

    /** @var Voucher $voucher */
    $voucher = $vouchers->first();

    // Set processed_on to a valid datetime string
    $now = now();
    $voucher->processed_on = $now->format('Y-m-d H:i:s');
    $voucher->save();

    // Verify the processed_on attribute and processed are set correctly
    expect($voucher->processed_on)->toBeInstanceOf(\DateTime::class);
    expect($voucher->processed_on->format('Y-m-d H:i:s'))->toBe($now->format('Y-m-d H:i:s'));
    expect($voucher->processed)->toBeTrue();
});

it('processed_on should return null if column is null', function () {
    // Arrange: Create a voucher using the Vouchers facade
    $instructions = [
        'prefix' => 'TEST',
        'mask' => '***-***',
        'metadata' => ['type' => 'test'],
        'ttl' => CarbonInterval::hours(1),
        'count' => 1,
    ];

    $vouchers = Vouchers::withPrefix($instructions['prefix'])
        ->withMask($instructions['mask'])
        ->withMetadata($instructions['metadata'])
        ->withExpireTimeIn($instructions['ttl'])
        ->create($instructions['count']);

    /** @var Voucher $voucher */
    $voucher = $vouchers->first();

    // Confirm processed_on is null and processed is false
    expect($voucher->processed_on)->toBeNull();
    expect($voucher->processed)->toBeFalse();
});
