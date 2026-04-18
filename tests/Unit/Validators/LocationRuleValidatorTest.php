<?php

use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Validators\LocationRuleValidator;

beforeEach(function () {
    $this->setupSystemUser();
});

it('supports vouchers that require location validation', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'location' => [
                'required' => true,
                'target_lat' => 14.5995,
                'target_lng' => 120.9842,
                'radius_meters' => 100,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $validator = app(LocationRuleValidator::class);

    expect($validator->supports($voucher))->toBeTrue();
});

it('does not support vouchers that do not require location validation', function () {
    $voucher = issueVoucher();

    $validator = app(LocationRuleValidator::class);

    expect($validator->supports($voucher))->toBeFalse();
});

it('returns no issues when location is present and within allowed radius', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'location' => [
                'required' => true,
                'target_lat' => 14.5995,
                'target_lng' => 120.9842,
                'radius_meters' => 500,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        latitude: 14.5996,
        longitude: 120.9843,
    );

    $validator = app(LocationRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toBeArray()->toHaveCount(0);
});

it('returns a missing issue when location is required but absent', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'location' => [
                'required' => true,
                'target_lat' => 14.5995,
                'target_lng' => 120.9842,
                'radius_meters' => 100,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        latitude: null,
        longitude: null,
    );

    $validator = app(LocationRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('location')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::MISSING)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::BLOCK);
});

it('returns an outside radius issue when location is outside the allowed radius', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'location' => [
                'required' => true,
                'target_lat' => 14.5995,
                'target_lng' => 120.9842,
                'radius_meters' => 50,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        latitude: 14.6095,
        longitude: 120.9942,
    );

    $validator = app(LocationRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('location')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::OUTSIDE_RADIUS)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::BLOCK);
});

it('returns a warn issue when location failure mode is warn', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'location' => [
                'required' => true,
                'target_lat' => 14.5995,
                'target_lng' => 120.9842,
                'radius_meters' => 50,
                'on_failure' => 'warn',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        latitude: null,
        longitude: null,
    );

    $validator = app(LocationRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('location')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::MISSING)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::WARN);
});