<?php

use LBHurtado\Voucher\Data\LocationValidationData;
use LBHurtado\Voucher\Data\LocationValidationResultData;

it('creates LocationValidationData with all fields', function () {
    $data = LocationValidationData::from([
        'required' => true,
        'target_lat' => 14.5995,
        'target_lng' => 120.9842,
        'radius_meters' => 100,
        'on_failure' => 'block',
    ]);

    expect($data->required)->toBeTrue()
        ->and($data->target_lat)->toBe(14.5995)
        ->and($data->target_lng)->toBe(120.9842)
        ->and($data->radius_meters)->toBe(100)
        ->and($data->on_failure)->toBe('block');
});

it('uses default on_failure value', function () {
    $data = LocationValidationData::from([
        'required' => true,
        'target_lat' => 14.5995,
        'target_lng' => 120.9842,
        'radius_meters' => 100,
    ]);

    expect($data->on_failure)->toBe('block');
});

it('validates location within radius', function () {
    $data = LocationValidationData::from([
        'required' => true,
        'target_lat' => 14.5995, // Manila
        'target_lng' => 120.9842,
        'radius_meters' => 1000, // 1km
        'on_failure' => 'block',
    ]);

    // Point 500m away (roughly)
    $result = $data->validateLocation(14.6040, 120.9842);

    expect($result)->toBeInstanceOf(LocationValidationResultData::class)
        ->and($result->validated)->toBeTrue()
        ->and($result->distance_meters)->toBeLessThan(1000)
        ->and($result->should_block)->toBeFalse();
});

it('validates location outside radius with block', function () {
    $data = LocationValidationData::from([
        'required' => true,
        'target_lat' => 14.5995,
        'target_lng' => 120.9842,
        'radius_meters' => 50, // 50m
        'on_failure' => 'block',
    ]);

    // Point ~5km away
    $result = $data->validateLocation(14.6500, 120.9842);

    expect($result->validated)->toBeFalse()
        ->and($result->distance_meters)->toBeGreaterThan(50)
        ->and($result->should_block)->toBeTrue();
});

it('validates location outside radius with warn', function () {
    $data = LocationValidationData::from([
        'required' => true,
        'target_lat' => 14.5995,
        'target_lng' => 120.9842,
        'radius_meters' => 50,
        'on_failure' => 'warn',
    ]);

    $result = $data->validateLocation(14.6500, 120.9842);

    expect($result->validated)->toBeFalse()
        ->and($result->distance_meters)->toBeGreaterThan(50)
        ->and($result->should_block)->toBeFalse(); // warn mode doesn't block
});

it('calculates distance accurately using Haversine formula', function () {
    $data = LocationValidationData::from([
        'required' => true,
        'target_lat' => 0.0,
        'target_lng' => 0.0,
        'radius_meters' => 100000,
        'on_failure' => 'block',
    ]);

    // 1 degree latitude ≈ 111km at equator
    $result = $data->validateLocation(1.0, 0.0);

    expect($result->distance_meters)->toBeGreaterThan(110000)
        ->and($result->distance_meters)->toBeLessThan(112000);
});

it('validates exact same location', function () {
    $data = LocationValidationData::from([
        'required' => true,
        'target_lat' => 14.5995,
        'target_lng' => 120.9842,
        'radius_meters' => 10,
        'on_failure' => 'block',
    ]);

    $result = $data->validateLocation(14.5995, 120.9842);

    expect($result->validated)->toBeTrue()
        ->and($result->distance_meters)->toBe(0.0)
        ->and($result->should_block)->toBeFalse();
});

// LocationValidationResultData tests

it('creates LocationValidationResultData', function () {
    $result = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 45.67,
        'should_block' => false,
    ]);

    expect($result->validated)->toBeTrue()
        ->and($result->distance_meters)->toBe(45.67)
        ->and($result->should_block)->toBeFalse();
});

it('checks if validation passed', function () {
    $passed = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 10.0,
        'should_block' => false,
    ]);

    $failed = LocationValidationResultData::from([
        'validated' => false,
        'distance_meters' => 100.0,
        'should_block' => true,
    ]);

    expect($passed->passed())->toBeTrue()
        ->and($passed->failed())->toBeFalse()
        ->and($failed->passed())->toBeFalse()
        ->and($failed->failed())->toBeTrue();
});

it('converts result to array', function () {
    $result = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 25.5,
        'should_block' => false,
    ]);

    $array = $result->toArray();

    expect($array)->toBe([
        'validated' => true,
        'distance_meters' => 25.5,
        'should_block' => false,
    ]);
});
