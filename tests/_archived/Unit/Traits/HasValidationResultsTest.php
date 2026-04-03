<?php

use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\LocationValidationResultData;
use LBHurtado\Voucher\Data\TimeValidationResultData;
use LBHurtado\Voucher\Data\ValidationResultsData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

function createVoucher(): \LBHurtado\Voucher\Models\Voucher
{
    $instructions = VoucherInstructionsData::generateFromScratch();
    $result = GenerateVouchers::run($instructions);

    return $result->vouchers->first();
}

it('stores and retrieves validation results', function () {
    $voucher = createVoucher();

    $location = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 25.5,
        'should_block' => false,
    ]);

    $time = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => false,
    ]);

    $voucher->storeValidationResults($location, $time);

    $results = $voucher->getValidationResults();

    expect($results)->toBeInstanceOf(ValidationResultsData::class)
        ->and($results->location->distance_meters)->toBe(25.5)
        ->and($results->time->duration_seconds)->toBe(120)
        ->and($results->passed)->toBeTrue();
});

it('checks if voucher has validation results', function () {
    $voucher = createVoucher();

    expect($voucher->hasValidationResults())->toBeFalse();

    $location = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 30.0,
        'should_block' => false,
    ]);

    $voucher->storeValidationResults($location);

    expect($voucher->hasValidationResults())->toBeTrue();
});

it('checks if voucher passed validation', function () {
    $voucher = createVoucher();

    $passing = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 30.0,
        'should_block' => false,
    ]);

    $failing = LocationValidationResultData::from([
        'validated' => false,
        'distance_meters' => 200.0,
        'should_block' => true,
    ]);

    $voucher->storeValidationResults($passing);
    expect($voucher->passedValidation())->toBeTrue();

    $voucher->storeValidationResults($failing);
    expect($voucher->passedValidation())->toBeFalse();
});

it('checks if voucher failed validation', function () {
    $voucher = createVoucher();

    $failing = LocationValidationResultData::from([
        'validated' => false,
        'distance_meters' => 200.0,
        'should_block' => true,
    ]);

    $voucher->storeValidationResults($failing);

    expect($voucher->failedValidation())->toBeTrue();
});

it('checks if voucher was blocked by validation', function () {
    $voucher = createVoucher();

    $blocked = LocationValidationResultData::from([
        'validated' => false,
        'distance_meters' => 200.0,
        'should_block' => true,
    ]);

    $warned = LocationValidationResultData::from([
        'validated' => false,
        'distance_meters' => 200.0,
        'should_block' => false,
    ]);

    $voucher->storeValidationResults($blocked);
    expect($voucher->wasBlockedByValidation())->toBeTrue();

    $voucher->storeValidationResults($warned);
    expect($voucher->wasBlockedByValidation())->toBeFalse();
});

it('gets location validation result', function () {
    $voucher = createVoucher();

    $location = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 15.5,
        'should_block' => false,
    ]);

    $voucher->storeValidationResults($location);

    $result = $voucher->getLocationValidationResult();

    expect($result)->toBeInstanceOf(LocationValidationResultData::class)
        ->and($result->distance_meters)->toBe(15.5);
});

it('gets time validation result', function () {
    $voucher = createVoucher();

    $time = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 90,
        'should_block' => false,
    ]);

    $voucher->storeValidationResults(null, $time);

    $result = $voucher->getTimeValidationResult();

    expect($result)->toBeInstanceOf(TimeValidationResultData::class)
        ->and($result->duration_seconds)->toBe(90);
});

it('gets failed validation types', function () {
    $voucher = createVoucher();

    $location = LocationValidationResultData::from([
        'validated' => false,
        'distance_meters' => 200.0,
        'should_block' => true,
    ]);

    $time = TimeValidationResultData::from([
        'within_window' => false,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => true,
    ]);

    $voucher->storeValidationResults($location, $time);

    expect($voucher->getFailedValidationTypes())->toBe(['location', 'time']);
});

it('gets passed validation types', function () {
    $voucher = createVoucher();

    $location = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 20.0,
        'should_block' => false,
    ]);

    $time = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 150,
        'should_block' => false,
    ]);

    $voucher->storeValidationResults($location, $time);

    expect($voucher->getPassedValidationTypes())->toBe(['location', 'time']);
});

it('gets validation summary', function () {
    $voucher = createVoucher();

    $location = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 42.0,
        'should_block' => false,
    ]);

    $voucher->storeValidationResults($location);

    $summary = $voucher->getValidationSummary();

    expect($summary['passed'])->toBeTrue()
        ->and($summary['location']['distance_meters'])->toBe(42.0)
        ->and($summary['time'])->toBeNull();
});

it('clears validation results', function () {
    $voucher = createVoucher();

    $location = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 25.0,
        'should_block' => false,
    ]);

    $voucher->storeValidationResults($location);
    expect($voucher->hasValidationResults())->toBeTrue();

    $voucher->clearValidationResults();
    expect($voucher->hasValidationResults())->toBeFalse();
});

it('sets validation results directly', function () {
    $voucher = createVoucher();

    $results = ValidationResultsData::from([
        'location' => LocationValidationResultData::from([
            'validated' => true,
            'distance_meters' => 35.0,
            'should_block' => false,
        ]),
        'passed' => true,
        'blocked' => false,
    ]);

    $voucher->setValidationResults($results);

    $retrieved = $voucher->getValidationResults();

    expect($retrieved->location->distance_meters)->toBe(35.0);
});

it('returns default summary when no results', function () {
    $voucher = createVoucher();

    $summary = $voucher->getValidationSummary();

    expect($summary['passed'])->toBeTrue()
        ->and($summary['blocked'])->toBeFalse()
        ->and($summary['location'])->toBeNull()
        ->and($summary['time'])->toBeNull();
});

it('returns true for passedValidation when no results', function () {
    $voucher = createVoucher();

    expect($voucher->passedValidation())->toBeTrue();
});

it('returns null for getLocationValidationResult when no results', function () {
    $voucher = createVoucher();

    expect($voucher->getLocationValidationResult())->toBeNull();
});

it('returns null for getTimeValidationResult when no results', function () {
    $voucher = createVoucher();

    expect($voucher->getTimeValidationResult())->toBeNull();
});
