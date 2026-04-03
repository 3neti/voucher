<?php

use Carbon\Carbon;
use LBHurtado\Voucher\Data\TimeWindowData;

it('creates TimeWindowData with all fields', function () {
    $data = TimeWindowData::from([
        'start_time' => '09:00',
        'end_time' => '17:00',
        'timezone' => 'Asia/Manila',
    ]);

    expect($data->start_time)->toBe('09:00')
        ->and($data->end_time)->toBe('17:00')
        ->and($data->timezone)->toBe('Asia/Manila');
});

it('uses default timezone', function () {
    $data = TimeWindowData::from([
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);

    expect($data->timezone)->toBe('Asia/Manila');
});

it('checks if time is within normal window', function () {
    $data = TimeWindowData::from([
        'start_time' => '09:00',
        'end_time' => '17:00',
        'timezone' => 'Asia/Manila',
    ]);

    // 12:00 is within 09:00-17:00
    $midday = Carbon::parse('2024-01-15 12:00:00', 'Asia/Manila');
    expect($data->isWithinWindow($midday))->toBeTrue();

    // 08:00 is before 09:00
    $early = Carbon::parse('2024-01-15 08:00:00', 'Asia/Manila');
    expect($data->isWithinWindow($early))->toBeFalse();

    // 18:00 is after 17:00
    $late = Carbon::parse('2024-01-15 18:00:00', 'Asia/Manila');
    expect($data->isWithinWindow($late))->toBeFalse();
});

it('checks if time is at boundary', function () {
    $data = TimeWindowData::from([
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);

    // Exactly at start
    $start = Carbon::parse('2024-01-15 09:00:00', 'Asia/Manila');
    expect($data->isWithinWindow($start))->toBeTrue();

    // Exactly at end
    $end = Carbon::parse('2024-01-15 17:00:00', 'Asia/Manila');
    expect($data->isWithinWindow($end))->toBeTrue();
});

it('handles cross-midnight window', function () {
    $data = TimeWindowData::from([
        'start_time' => '22:00',
        'end_time' => '02:00',
        'timezone' => 'Asia/Manila',
    ]);

    // 23:00 is within window (after start)
    $evening = Carbon::parse('2024-01-15 23:00:00', 'Asia/Manila');
    expect($data->isWithinWindow($evening))->toBeTrue();

    // 01:00 is within window (before end, next day)
    $midnight = Carbon::parse('2024-01-15 01:00:00', 'Asia/Manila');
    expect($data->isWithinWindow($midnight))->toBeTrue();

    // 12:00 is outside window
    $midday = Carbon::parse('2024-01-15 12:00:00', 'Asia/Manila');
    expect($data->isWithinWindow($midday))->toBeFalse();

    // 03:00 is outside window (after end)
    $afterEnd = Carbon::parse('2024-01-15 03:00:00', 'Asia/Manila');
    expect($data->isWithinWindow($afterEnd))->toBeFalse();
});

it('detects if window spans midnight', function () {
    $normal = TimeWindowData::from([
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);

    $crossMidnight = TimeWindowData::from([
        'start_time' => '22:00',
        'end_time' => '02:00',
    ]);

    expect($normal->spansMidnight())->toBeFalse()
        ->and($crossMidnight->spansMidnight())->toBeTrue();
});

it('gets start time as Carbon instance', function () {
    $data = TimeWindowData::from([
        'start_time' => '09:30',
        'end_time' => '17:00',
    ]);

    $date = Carbon::parse('2024-01-15', 'Asia/Manila');
    $start = $data->getStartTime($date);

    expect($start->format('H:i'))->toBe('09:30')
        ->and($start->format('Y-m-d'))->toBe('2024-01-15');
});

it('gets end time as Carbon instance', function () {
    $data = TimeWindowData::from([
        'start_time' => '09:00',
        'end_time' => '17:30',
    ]);

    $date = Carbon::parse('2024-01-15', 'Asia/Manila');
    $end = $data->getEndTime($date);

    expect($end->format('H:i'))->toBe('17:30')
        ->and($end->format('Y-m-d'))->toBe('2024-01-15');
});

it('handles whole day window', function () {
    $data = TimeWindowData::from([
        'start_time' => '00:00',
        'end_time' => '23:59',
    ]);

    $morning = Carbon::parse('2024-01-15 06:00:00', 'Asia/Manila');
    $evening = Carbon::parse('2024-01-15 22:00:00', 'Asia/Manila');
    $midnight = Carbon::parse('2024-01-15 23:59:00', 'Asia/Manila');

    expect($data->isWithinWindow($morning))->toBeTrue()
        ->and($data->isWithinWindow($evening))->toBeTrue()
        ->and($data->isWithinWindow($midnight))->toBeTrue();
});

it('respects timezone', function () {
    $manila = TimeWindowData::from([
        'start_time' => '09:00',
        'end_time' => '17:00',
        'timezone' => 'Asia/Manila',
    ]);

    $utc = TimeWindowData::from([
        'start_time' => '09:00',
        'end_time' => '17:00',
        'timezone' => 'UTC',
    ]);

    // Same absolute time, different timezones
    // Manila is UTC+8, so 09:00 Manila = 01:00 UTC
    $time = Carbon::parse('2024-01-15 09:00:00', 'Asia/Manila');

    expect($manila->isWithinWindow($time))->toBeTrue();
    // In UTC, this time would be 01:00 which is outside 09:00-17:00 UTC
    expect($utc->isWithinWindow($time))->toBeFalse();
});
