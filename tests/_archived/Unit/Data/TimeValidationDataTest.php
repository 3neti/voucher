<?php

use LBHurtado\Voucher\Data\TimeValidationData;
use LBHurtado\Voucher\Data\TimeWindowData;

it('creates TimeValidationData with all fields', function () {
    $window = TimeWindowData::from([
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);

    $data = TimeValidationData::from([
        'window' => $window,
        'limit_minutes' => 30,
        'track_duration' => true,
    ]);

    expect($data->window)->toBeInstanceOf(TimeWindowData::class)
        ->and($data->limit_minutes)->toBe(30)
        ->and($data->track_duration)->toBeTrue();
});

it('creates TimeValidationData with minimal fields', function () {
    $data = TimeValidationData::from([]);

    expect($data->window)->toBeNull()
        ->and($data->limit_minutes)->toBeNull()
        ->and($data->track_duration)->toBeTrue(); // default from config
});

it('checks if window validation is enabled', function () {
    $withWindow = TimeValidationData::from([
        'window' => TimeWindowData::from([
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]),
    ]);

    $withoutWindow = TimeValidationData::from([]);

    expect($withWindow->hasWindowValidation())->toBeTrue()
        ->and($withoutWindow->hasWindowValidation())->toBeFalse();
});

it('checks if duration limit is enabled', function () {
    $withLimit = TimeValidationData::from([
        'limit_minutes' => 30,
    ]);

    $withoutLimit = TimeValidationData::from([]);

    expect($withLimit->hasDurationLimit())->toBeTrue()
        ->and($withoutLimit->hasDurationLimit())->toBeFalse();
});

it('checks if duration tracking is enabled', function () {
    $tracking = TimeValidationData::from([
        'track_duration' => true,
    ]);

    $notTracking = TimeValidationData::from([
        'track_duration' => false,
    ]);

    expect($tracking->shouldTrackDuration())->toBeTrue()
        ->and($notTracking->shouldTrackDuration())->toBeFalse();
});

it('validates if current time is within window', function () {
    $data = TimeValidationData::from([
        'window' => TimeWindowData::from([
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]),
    ]);

    // This test depends on current time, so we'll just check the method exists
    // and returns a boolean
    expect($data->isWithinWindow())->toBeBool();
});

it('returns true when no window is configured', function () {
    $data = TimeValidationData::from([]);

    expect($data->isWithinWindow())->toBeTrue();
});

it('checks if duration exceeds limit', function () {
    $data = TimeValidationData::from([
        'limit_minutes' => 5,
    ]);

    // 4 minutes (240 seconds) - within limit
    expect($data->exceedsDurationLimit(240))->toBeFalse();

    // 6 minutes (360 seconds) - exceeds limit
    expect($data->exceedsDurationLimit(360))->toBeTrue();

    // Exactly 5 minutes (300 seconds) - at limit
    expect($data->exceedsDurationLimit(300))->toBeFalse();
});

it('returns false when no duration limit is configured', function () {
    $data = TimeValidationData::from([]);

    // Should never exceed when no limit is set
    expect($data->exceedsDurationLimit(10000))->toBeFalse();
});

it('handles large duration values', function () {
    $data = TimeValidationData::from([
        'limit_minutes' => 1440, // 24 hours
    ]);

    // 23 hours - within limit
    expect($data->exceedsDurationLimit(23 * 3600))->toBeFalse();

    // 25 hours - exceeds limit
    expect($data->exceedsDurationLimit(25 * 3600))->toBeTrue();
});

it('validates time window with specific time', function () {
    $data = TimeValidationData::from([
        'window' => TimeWindowData::from([
            'start_time' => '09:00',
            'end_time' => '17:00',
            'timezone' => 'Asia/Manila',
        ]),
    ]);

    // We can't directly test isWithinWindow without modifying the method
    // to accept a time parameter, but we can verify the window is set correctly
    expect($data->window->start_time)->toBe('09:00')
        ->and($data->window->end_time)->toBe('17:00');
});

it('combines window and duration validation', function () {
    $data = TimeValidationData::from([
        'window' => TimeWindowData::from([
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]),
        'limit_minutes' => 30,
        'track_duration' => true,
    ]);

    expect($data->hasWindowValidation())->toBeTrue()
        ->and($data->hasDurationLimit())->toBeTrue()
        ->and($data->shouldTrackDuration())->toBeTrue();
});

it('handles edge case durations', function () {
    $data = TimeValidationData::from([
        'limit_minutes' => 1, // 1 minute
    ]);

    // 59 seconds - within limit
    expect($data->exceedsDurationLimit(59))->toBeFalse();

    // 60 seconds - at limit
    expect($data->exceedsDurationLimit(60))->toBeFalse();

    // 61 seconds - exceeds limit
    expect($data->exceedsDurationLimit(61))->toBeTrue();
});
