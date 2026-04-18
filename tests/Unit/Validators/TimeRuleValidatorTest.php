<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Validators\TimeRuleValidator;

beforeEach(function () {
    $this->setupSystemUser();
});

afterEach(function () {
    Date::setTestNow();
});

it('supports vouchers with a time window', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'time' => [
                'window' => [
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'timezone' => config('app.timezone', 'UTC'),
                ],
            ],
        ],
    ]));

    $validator = app(TimeRuleValidator::class);

    expect($validator->supports($voucher))->toBeTrue();
});

it('supports vouchers with a time limit', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'time' => [
                'limit_minutes' => 30,
                'track_duration' => true,
            ],
        ],
    ]));

    $validator = app(TimeRuleValidator::class);

    expect($validator->supports($voucher))->toBeTrue();
});

it('does not support vouchers without time validation', function () {
    $voucher = issueVoucher();

    $validator = app(TimeRuleValidator::class);

    expect($validator->supports($voucher))->toBeFalse();
});

it('returns no issues when redemption is within the allowed time window', function () {
    Date::setTestNow(now()->setTime(10, 0, 0));

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'time' => [
                'window' => [
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'timezone' => config('app.timezone', 'UTC'),
                ],
            ],
        ],
    ]));

    $validator = app(TimeRuleValidator::class);

    $issues = $validator->validate($voucher, new RedemptionEvidenceData());

    expect($issues)->toBeArray()->toHaveCount(0);
});

it('returns an outside_time_window issue when redemption is outside the allowed window', function () {
    Date::setTestNow(now()->setTime(23, 30, 0));

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'time' => [
                'window' => [
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'timezone' => config('app.timezone', 'UTC'),
                ],
            ],
        ],
    ]));

    $validator = app(TimeRuleValidator::class);

    $issues = $validator->validate($voucher, new RedemptionEvidenceData());

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('time')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::OUTSIDE_TIME_WINDOW)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::BLOCK);
});

it('returns no issues when redemption is within the allowed duration limit', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'starts_at' => now()->subMinutes(15)->toIso8601String(),
        'validation' => [
            'time' => [
                'limit_minutes' => 30,
                'track_duration' => true,
            ],
        ],
    ]));

    $validator = app(TimeRuleValidator::class);

    $issues = $validator->validate($voucher, new RedemptionEvidenceData());

    expect($issues)->toBeArray()->toHaveCount(0);
});

it('returns a time_limit_exceeded issue when redemption exceeds the duration limit', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'starts_at' => now()->subMinutes(45)->toIso8601String(),
        'validation' => [
            'time' => [
                'limit_minutes' => 30,
                'track_duration' => true,
            ],
        ],
    ]));

    $validator = app(TimeRuleValidator::class);

    $issues = $validator->validate($voucher, new RedemptionEvidenceData());

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('time')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::TIME_LIMIT_EXCEEDED)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::BLOCK);
});

it('supports overnight time windows correctly', function () {
    Date::setTestNow(now()->setTime(1, 30, 0));

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'time' => [
                'window' => [
                    'start_time' => '22:00',
                    'end_time' => '02:00',
                    'timezone' => config('app.timezone', 'UTC'),
                ],
            ],
        ],
    ]));

    $validator = app(TimeRuleValidator::class);

    $issues = $validator->validate($voucher, new RedemptionEvidenceData());

    expect($issues)->toBeArray()->toHaveCount(0);
});