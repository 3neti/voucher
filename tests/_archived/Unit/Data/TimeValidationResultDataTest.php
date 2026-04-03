<?php

use LBHurtado\Voucher\Data\TimeValidationResultData;

it('creates TimeValidationResultData with all fields', function () {
    $data = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 180,
        'should_block' => false,
    ]);

    expect($data->within_window)->toBeTrue()
        ->and($data->within_duration)->toBeTrue()
        ->and($data->duration_seconds)->toBe(180)
        ->and($data->should_block)->toBeFalse();
});

it('checks if all validations passed', function () {
    $allPassed = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => false,
    ]);

    $windowFailed = TimeValidationResultData::from([
        'within_window' => false,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => true,
    ]);

    $durationFailed = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => false,
        'duration_seconds' => 600,
        'should_block' => true,
    ]);

    expect($allPassed->passed())->toBeTrue()
        ->and($windowFailed->passed())->toBeFalse()
        ->and($durationFailed->passed())->toBeFalse();
});

it('checks if any validation failed', function () {
    $passed = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => false,
    ]);

    $failed = TimeValidationResultData::from([
        'within_window' => false,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => true,
    ]);

    expect($passed->failed())->toBeFalse()
        ->and($failed->failed())->toBeTrue();
});

it('converts to array', function () {
    $data = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => false,
        'duration_seconds' => 400,
        'should_block' => true,
    ]);

    expect($data->toArray())->toBe([
        'within_window' => true,
        'within_duration' => false,
        'duration_seconds' => 400,
        'should_block' => true,
    ]);
});

it('gets duration in minutes', function () {
    $data = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 300, // 5 minutes
        'should_block' => false,
    ]);

    expect($data->getDurationMinutes())->toBe(5.0);
});

it('handles fractional minutes', function () {
    $data = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 90, // 1.5 minutes
        'should_block' => false,
    ]);

    expect($data->getDurationMinutes())->toBe(1.5);
});

it('checks window validation status', function () {
    $withinWindow = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => false,
        'duration_seconds' => 600,
        'should_block' => true,
    ]);

    $outsideWindow = TimeValidationResultData::from([
        'within_window' => false,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => true,
    ]);

    expect($withinWindow->passedWindowValidation())->toBeTrue()
        ->and($outsideWindow->passedWindowValidation())->toBeFalse();
});

it('checks duration validation status', function () {
    $withinDuration = TimeValidationResultData::from([
        'within_window' => false,
        'within_duration' => true,
        'duration_seconds' => 120,
        'should_block' => true,
    ]);

    $exceededDuration = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => false,
        'duration_seconds' => 600,
        'should_block' => true,
    ]);

    expect($withinDuration->passedDurationValidation())->toBeTrue()
        ->and($exceededDuration->passedDurationValidation())->toBeFalse();
});

it('handles zero duration', function () {
    $data = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => true,
        'duration_seconds' => 0,
        'should_block' => false,
    ]);

    expect($data->duration_seconds)->toBe(0)
        ->and($data->getDurationMinutes())->toBe(0.0);
});

it('handles large durations', function () {
    $data = TimeValidationResultData::from([
        'within_window' => true,
        'within_duration' => false,
        'duration_seconds' => 86400, // 24 hours
        'should_block' => true,
    ]);

    expect($data->getDurationMinutes())->toBe(1440.0); // 24 * 60
});
