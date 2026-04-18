<?php

use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Validators\SelfieRuleValidator;

beforeEach(function () {
    $this->setupSystemUser();
});

it('supports vouchers that require selfie validation', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'selfie' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $validator = app(SelfieRuleValidator::class);

    expect($validator->supports($voucher))->toBeTrue();
});

it('does not support vouchers that do not require selfie validation', function () {
    $voucher = issueVoucher();

    $validator = app(SelfieRuleValidator::class);

    expect($validator->supports($voucher))->toBeFalse();
});

it('returns no issues when selfie is present', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'selfie' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        selfie: 'data:image/jpeg;base64,FAKE_SELFIE',
    );

    $validator = app(SelfieRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toBeArray()->toHaveCount(0);
});

it('returns a block issue when selfie is required but missing', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'selfie' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        selfie: null,
    );

    $validator = app(SelfieRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('selfie')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::MISSING)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::BLOCK);
});

it('returns a warn issue when selfie failure mode is warn', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'selfie' => [
                'required' => true,
                'on_failure' => 'warn',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        selfie: '',
    );

    $validator = app(SelfieRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('selfie')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::MISSING)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::WARN);
});