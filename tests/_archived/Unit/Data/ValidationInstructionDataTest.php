<?php

use LBHurtado\Voucher\Data\LocationValidationData;
use LBHurtado\Voucher\Data\TimeValidationData;
use LBHurtado\Voucher\Data\TimeWindowData;
use LBHurtado\Voucher\Data\ValidationInstructionData;

it('creates ValidationInstructionData with all fields', function () {
    $location = LocationValidationData::from([
        'required' => true,
        'target_lat' => 14.5995,
        'target_lng' => 120.9842,
        'radius_meters' => 100,
        'on_failure' => 'block',
    ]);

    $time = TimeValidationData::from([
        'window' => TimeWindowData::from([
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]),
        'limit_minutes' => 30,
        'track_duration' => true,
    ]);

    $data = ValidationInstructionData::from([
        'location' => $location,
        'time' => $time,
    ]);

    expect($data->location)->toBeInstanceOf(LocationValidationData::class)
        ->and($data->time)->toBeInstanceOf(TimeValidationData::class);
});

it('creates ValidationInstructionData with no validations', function () {
    $data = ValidationInstructionData::from([]);

    expect($data->location)->toBeNull()
        ->and($data->time)->toBeNull();
});

it('creates ValidationInstructionData with only location', function () {
    $data = ValidationInstructionData::from([
        'location' => LocationValidationData::from([
            'required' => true,
            'target_lat' => 14.5995,
            'target_lng' => 120.9842,
            'radius_meters' => 100,
        ]),
    ]);

    expect($data->location)->toBeInstanceOf(LocationValidationData::class)
        ->and($data->time)->toBeNull();
});

it('creates ValidationInstructionData with only time', function () {
    $data = ValidationInstructionData::from([
        'time' => TimeValidationData::from([
            'limit_minutes' => 30,
        ]),
    ]);

    expect($data->location)->toBeNull()
        ->and($data->time)->toBeInstanceOf(TimeValidationData::class);
});

it('checks if location validation is configured', function () {
    $withLocation = ValidationInstructionData::from([
        'location' => LocationValidationData::from([
            'required' => true,
            'target_lat' => 14.5995,
            'target_lng' => 120.9842,
            'radius_meters' => 100,
        ]),
    ]);

    $withoutLocation = ValidationInstructionData::from([]);

    expect($withLocation->hasLocationValidation())->toBeTrue()
        ->and($withoutLocation->hasLocationValidation())->toBeFalse();
});

it('checks if time validation is configured', function () {
    $withTime = ValidationInstructionData::from([
        'time' => TimeValidationData::from([
            'limit_minutes' => 30,
        ]),
    ]);

    $withoutTime = ValidationInstructionData::from([]);

    expect($withTime->hasTimeValidation())->toBeTrue()
        ->and($withoutTime->hasTimeValidation())->toBeFalse();
});

it('checks if any validation is configured', function () {
    $withBoth = ValidationInstructionData::from([
        'location' => LocationValidationData::from([
            'required' => true,
            'target_lat' => 14.5995,
            'target_lng' => 120.9842,
            'radius_meters' => 100,
        ]),
        'time' => TimeValidationData::from([
            'limit_minutes' => 30,
        ]),
    ]);

    $withLocation = ValidationInstructionData::from([
        'location' => LocationValidationData::from([
            'required' => true,
            'target_lat' => 14.5995,
            'target_lng' => 120.9842,
            'radius_meters' => 100,
        ]),
    ]);

    $withTime = ValidationInstructionData::from([
        'time' => TimeValidationData::from([
            'limit_minutes' => 30,
        ]),
    ]);

    $withNone = ValidationInstructionData::from([]);

    expect($withBoth->hasAnyValidation())->toBeTrue()
        ->and($withLocation->hasAnyValidation())->toBeTrue()
        ->and($withTime->hasAnyValidation())->toBeTrue()
        ->and($withNone->hasAnyValidation())->toBeFalse();
});

it('gets enabled validation types', function () {
    $withBoth = ValidationInstructionData::from([
        'location' => LocationValidationData::from([
            'required' => true,
            'target_lat' => 14.5995,
            'target_lng' => 120.9842,
            'radius_meters' => 100,
        ]),
        'time' => TimeValidationData::from([
            'limit_minutes' => 30,
        ]),
    ]);

    $withLocation = ValidationInstructionData::from([
        'location' => LocationValidationData::from([
            'required' => true,
            'target_lat' => 14.5995,
            'target_lng' => 120.9842,
            'radius_meters' => 100,
        ]),
    ]);

    $withTime = ValidationInstructionData::from([
        'time' => TimeValidationData::from([
            'limit_minutes' => 30,
        ]),
    ]);

    $withNone = ValidationInstructionData::from([]);

    expect($withBoth->getEnabledValidations())->toBe(['location', 'time'])
        ->and($withLocation->getEnabledValidations())->toBe(['location'])
        ->and($withTime->getEnabledValidations())->toBe(['time'])
        ->and($withNone->getEnabledValidations())->toBe([]);
});

it('handles nested validation data correctly', function () {
    $data = ValidationInstructionData::from([
        'location' => [
            'required' => true,
            'target_lat' => 14.5995,
            'target_lng' => 120.9842,
            'radius_meters' => 50,
            'on_failure' => 'warn',
        ],
        'time' => [
            'window' => [
                'start_time' => '08:00',
                'end_time' => '18:00',
                'timezone' => 'Asia/Manila',
            ],
            'limit_minutes' => 45,
            'track_duration' => false,
        ],
    ]);

    expect($data->location->target_lat)->toBe(14.5995)
        ->and($data->location->radius_meters)->toBe(50)
        ->and($data->location->on_failure)->toBe('warn')
        ->and($data->time->limit_minutes)->toBe(45)
        ->and($data->time->track_duration)->toBeFalse()
        ->and($data->time->window->start_time)->toBe('08:00');
});
