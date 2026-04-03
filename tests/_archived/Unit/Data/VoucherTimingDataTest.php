<?php

use Carbon\Carbon;
use LBHurtado\Voucher\Data\VoucherTimingData;

test('can create with all fields', function () {
    $data = VoucherTimingData::from([
        'clicked_at' => '2025-01-15T10:00:00Z',
        'started_at' => '2025-01-15T10:00:05Z',
        'submitted_at' => '2025-01-15T10:02:30Z',
        'duration_seconds' => 145,
    ]);

    expect($data->clicked_at)->toBe('2025-01-15T10:00:00Z')
        ->and($data->started_at)->toBe('2025-01-15T10:00:05Z')
        ->and($data->submitted_at)->toBe('2025-01-15T10:02:30Z')
        ->and($data->duration_seconds)->toBe(145);
});

test('can create with null fields', function () {
    $data = VoucherTimingData::from([]);

    expect($data->clicked_at)->toBeNull()
        ->and($data->started_at)->toBeNull()
        ->and($data->submitted_at)->toBeNull()
        ->and($data->duration_seconds)->toBeNull();
});

test('get clicked at returns Carbon instance', function () {
    $data = VoucherTimingData::from([
        'clicked_at' => '2025-01-15T10:00:00Z',
    ]);

    $carbon = $data->getClickedAt();

    expect($carbon)->toBeInstanceOf(Carbon::class)
        ->and($carbon->toIso8601String())->toBe('2025-01-15T10:00:00+00:00');
});

test('get clicked at returns null when not set', function () {
    $data = VoucherTimingData::from([]);

    expect($data->getClickedAt())->toBeNull();
});

test('calculate duration returns seconds between start and submit', function () {
    $data = VoucherTimingData::from([
        'started_at' => '2025-01-15T10:00:00Z',
        'submitted_at' => '2025-01-15T10:02:30Z',
    ]);

    expect($data->calculateDuration())->toBe(150); // 2 minutes 30 seconds
});

test('calculate duration returns null when timestamps missing', function () {
    $data1 = VoucherTimingData::from(['started_at' => '2025-01-15T10:00:00Z']);
    $data2 = VoucherTimingData::from(['submitted_at' => '2025-01-15T10:02:30Z']);
    $data3 = VoucherTimingData::from([]);

    expect($data1->calculateDuration())->toBeNull()
        ->and($data2->calculateDuration())->toBeNull()
        ->and($data3->calculateDuration())->toBeNull();
});

test('was clicked returns true when clicked at is set', function () {
    $data = VoucherTimingData::from(['clicked_at' => '2025-01-15T10:00:00Z']);

    expect($data->wasClicked())->toBeTrue();
});

test('was clicked returns false when not set', function () {
    $data = VoucherTimingData::from([]);

    expect($data->wasClicked())->toBeFalse();
});

test('was started returns true when started at is set', function () {
    $data = VoucherTimingData::from(['started_at' => '2025-01-15T10:00:00Z']);

    expect($data->wasStarted())->toBeTrue();
});

test('was submitted returns true when submitted at is set', function () {
    $data = VoucherTimingData::from(['submitted_at' => '2025-01-15T10:00:00Z']);

    expect($data->wasSubmitted())->toBeTrue();
});

test('with click creates new instance with click timestamp', function () {
    $data = VoucherTimingData::withClick();

    expect($data->clicked_at)->not->toBeNull()
        ->and($data->wasClicked())->toBeTrue()
        ->and($data->started_at)->toBeNull()
        ->and($data->submitted_at)->toBeNull();
});

test('with start preserves existing data and adds start timestamp', function () {
    $original = VoucherTimingData::from(['clicked_at' => '2025-01-15T10:00:00Z']);

    $modified = $original->withStart();

    expect($modified->clicked_at)->toBe('2025-01-15T10:00:00Z')
        ->and($modified->started_at)->not->toBeNull()
        ->and($modified->wasStarted())->toBeTrue();
});

test('with submit preserves data adds submit and calculates duration', function () {
    // Set a fixed start time
    $startTime = now()->subMinutes(2);

    $original = VoucherTimingData::from([
        'clicked_at' => $startTime->subSeconds(5)->toIso8601String(),
        'started_at' => $startTime->toIso8601String(),
    ]);

    $modified = $original->withSubmit();

    expect($modified->clicked_at)->toBe($original->clicked_at)
        ->and($modified->started_at)->toBe($original->started_at)
        ->and($modified->submitted_at)->not->toBeNull()
        ->and($modified->wasSubmitted())->toBeTrue()
        ->and($modified->duration_seconds)->toBeGreaterThan(100); // At least ~2 minutes
});

test('to array includes all fields', function () {
    $data = VoucherTimingData::from([
        'clicked_at' => '2025-01-15T10:00:00Z',
        'started_at' => '2025-01-15T10:00:05Z',
        'duration_seconds' => 145,
    ]);

    $array = $data->toArray();

    expect($array['clicked_at'])->toBe('2025-01-15T10:00:00Z')
        ->and($array['started_at'])->toBe('2025-01-15T10:00:05Z')
        ->and($array['submitted_at'])->toBeNull()
        ->and($array['duration_seconds'])->toBe(145);
});

test('validates duration seconds is non negative', function () {
    VoucherTimingData::validateAndCreate([
        'duration_seconds' => -1,
    ]);
})->throws(\Illuminate\Validation\ValidationException::class);
