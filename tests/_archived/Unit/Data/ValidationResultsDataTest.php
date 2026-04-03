<?php

use LBHurtado\Voucher\Data\LocationValidationResultData;
use LBHurtado\Voucher\Data\TimeValidationResultData;
use LBHurtado\Voucher\Data\ValidationResultsData;

it('creates ValidationResultsData with all results', function () {
    $location = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 45.5,
        'should_block' => false,
    ]);

    $time = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => false,
    ]);

    $data = ValidationResultsData::from([
        'location' => $location,
        'time' => $time,
        'passed' => true,
        'blocked' => false,
    ]);

    expect($data->location)->toBeInstanceOf(LocationValidationResultData::class)
        ->and($data->time)->toBeInstanceOf(TimeValidationResultData::class)
        ->and($data->passed)->toBeTrue()
        ->and($data->blocked)->toBeFalse();
});

it('creates ValidationResultsData with no results', function () {
    $data = ValidationResultsData::from([]);

    expect($data->location)->toBeNull()
        ->and($data->time)->toBeNull()
        ->and($data->passed)->toBeTrue()
        ->and($data->blocked)->toBeFalse();
});

it('creates from validations with all passing', function () {
    $location = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 30.0,
        'should_block' => false,
    ]);

    $time = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 180,
        'should_block' => false,
    ]);

    $data = ValidationResultsData::fromValidations($location, $time);

    expect($data->passed)->toBeTrue()
        ->and($data->blocked)->toBeFalse();
});

it('creates from validations with location failure', function () {
    $location = LocationValidationResultData::from([
        'validated' => false,
        'distance_meters' => 150.0,
        'should_block' => true,
    ]);

    $time = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => false,
    ]);

    $data = ValidationResultsData::fromValidations($location, $time);

    expect($data->passed)->toBeFalse()
        ->and($data->blocked)->toBeTrue();
});

it('creates from validations with time failure', function () {
    $location = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 30.0,
        'should_block' => false,
    ]);

    $time = TimeValidationResultData::from([
        'within_window' => false,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => true,
    ]);

    $data = ValidationResultsData::fromValidations($location, $time);

    expect($data->passed)->toBeFalse()
        ->and($data->blocked)->toBeTrue();
});

it('checks if location results exist', function () {
    $withLocation = ValidationResultsData::from([
        'location' => LocationValidationResultData::from([
            'validated' => true,
            'distance_meters' => 20.0,
            'should_block' => false,
        ]),
    ]);

    $withoutLocation = ValidationResultsData::from([]);

    expect($withLocation->hasLocationResults())->toBeTrue()
        ->and($withoutLocation->hasLocationResults())->toBeFalse();
});

it('checks if time results exist', function () {
    $withTime = ValidationResultsData::from([
        'time' => TimeValidationResultData::from([
            'within_window' => true,
            'within_duration' => true,
            'duration_seconds' => 120,
            'should_block' => false,
        ]),
    ]);

    $withoutTime = ValidationResultsData::from([]);

    expect($withTime->hasTimeResults())->toBeTrue()
        ->and($withoutTime->hasTimeResults())->toBeFalse();
});

it('checks if any results exist', function () {
    $withBoth = ValidationResultsData::from([
        'location' => LocationValidationResultData::from([
            'validated' => true,
            'distance_meters' => 20.0,
            'should_block' => false,
        ]),
        'time' => TimeValidationResultData::from([
            'within_window' => true,
            'within_duration' => true,
            'duration_seconds' => 120,
            'should_block' => false,
        ]),
    ]);

    $withNone = ValidationResultsData::from([]);

    expect($withBoth->hasAnyResults())->toBeTrue()
        ->and($withNone->hasAnyResults())->toBeFalse();
});

it('checks if all validations passed', function () {
    $passed = ValidationResultsData::from(['passed' => true]);
    $failed = ValidationResultsData::from(['passed' => false]);

    expect($passed->allPassed())->toBeTrue()
        ->and($failed->allPassed())->toBeFalse();
});

it('checks if any validation failed', function () {
    $passed = ValidationResultsData::from(['passed' => true]);
    $failed = ValidationResultsData::from(['passed' => false]);

    expect($passed->anyFailed())->toBeFalse()
        ->and($failed->anyFailed())->toBeTrue();
});

it('checks if should block', function () {
    $blocked = ValidationResultsData::from(['blocked' => true]);
    $notBlocked = ValidationResultsData::from(['blocked' => false]);

    expect($blocked->shouldBlock())->toBeTrue()
        ->and($notBlocked->shouldBlock())->toBeFalse();
});

it('gets failed validation types', function () {
    $location = LocationValidationResultData::from([
        'validated' => false,
        'distance_meters' => 150.0,
        'should_block' => true,
    ]);

    $time = TimeValidationResultData::from([
        'within_window' => false,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => true,
    ]);

    $bothFailed = ValidationResultsData::fromValidations($location, $time);
    $locationOnly = ValidationResultsData::fromValidations($location, null);
    $timeOnly = ValidationResultsData::fromValidations(null, $time);
    $noneFailed = ValidationResultsData::from([]);

    expect($bothFailed->getFailedValidations())->toBe(['location', 'time'])
        ->and($locationOnly->getFailedValidations())->toBe(['location'])
        ->and($timeOnly->getFailedValidations())->toBe(['time'])
        ->and($noneFailed->getFailedValidations())->toBe([]);
});

it('gets passed validation types', function () {
    $location = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 30.0,
        'should_block' => false,
    ]);

    $time = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => false,
    ]);

    $bothPassed = ValidationResultsData::fromValidations($location, $time);

    expect($bothPassed->getPassedValidations())->toBe(['location', 'time']);
});

it('gets validation summary', function () {
    $location = LocationValidationResultData::from([
        'validated' => true,
        'distance_meters' => 25.5,
        'should_block' => false,
    ]);

    $time = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 150,
        'should_block' => false,
    ]);

    $data = ValidationResultsData::fromValidations($location, $time);
    $summary = $data->getSummary();

    expect($summary['passed'])->toBeTrue()
        ->and($summary['blocked'])->toBeFalse()
        ->and($summary['location']['validated'])->toBeTrue()
        ->and($summary['location']['distance_meters'])->toBe(25.5)
        ->and($summary['time']['within_window'])->toBeTrue()
        ->and($summary['time']['duration_seconds'])->toBe(150);
});

it('handles null results in summary', function () {
    $data = ValidationResultsData::from([]);
    $summary = $data->getSummary();

    expect($summary['passed'])->toBeTrue()
        ->and($summary['blocked'])->toBeFalse()
        ->and($summary['location'])->toBeNull()
        ->and($summary['time'])->toBeNull();
});
