<?php

use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Validators\RequiredInputFieldsValidator;

beforeEach(function () {
    $this->setupSystemUser();
});

it('supports vouchers with declared input fields', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['signature', 'selfie'],
        ],
    ]));

    $validator = app(RequiredInputFieldsValidator::class);

    expect($validator->supports($voucher))->toBeTrue();
});

it('does not support vouchers with no declared input fields', function () {
    $voucher = issueVoucher();

    $validator = app(RequiredInputFieldsValidator::class);

    expect($validator->supports($voucher))->toBeFalse();
});

it('returns no issues when all declared input fields are present', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['signature', 'selfie', 'otp', 'location'],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        signature: 'data:image/png;base64,FAKE_SIGNATURE',
        selfie: 'data:image/jpeg;base64,FAKE_SELFIE',
        otp: '123456',
        latitude: 14.5995,
        longitude: 120.9842,
    );

    $validator = app(RequiredInputFieldsValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toBeArray()->toHaveCount(0);
});

it('returns a missing issue when signature is declared but absent', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['signature'],
        ],
    ]));

    $validator = app(RequiredInputFieldsValidator::class);

    $issues = $validator->validate($voucher, new RedemptionEvidenceData());

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('signature')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::REQUIRED_INPUT_MISSING)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::BLOCK);
});

it('returns a missing issue when selfie is declared but absent', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['selfie'],
        ],
    ]));

    $validator = app(RequiredInputFieldsValidator::class);

    $issues = $validator->validate($voucher, new RedemptionEvidenceData());

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('selfie')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::REQUIRED_INPUT_MISSING);
});

it('returns a missing issue when otp is declared but absent', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp'],
        ],
    ]));

    $validator = app(RequiredInputFieldsValidator::class);

    $issues = $validator->validate($voucher, new RedemptionEvidenceData());

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('otp')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::REQUIRED_INPUT_MISSING);
});

it('returns a missing issue when location is declared but absent', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['location'],
        ],
    ]));

    $validator = app(RequiredInputFieldsValidator::class);

    $issues = $validator->validate($voucher, new RedemptionEvidenceData());

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('location')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::REQUIRED_INPUT_MISSING);
});

it('returns multiple missing issues for multiple absent declared inputs', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['signature', 'selfie', 'otp'],
        ],
    ]));

    $validator = app(RequiredInputFieldsValidator::class);

    $issues = $validator->validate($voucher, new RedemptionEvidenceData());

    expect($issues)->toHaveCount(3)
        ->and(collect($issues)->pluck('field')->all())->toBe(['signature', 'selfie', 'otp']);
});

it('treats kyc as present when kyc payload exists', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['kyc'],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        kyc: [
            'face_verification' => [
                'verified' => true,
            ],
        ],
    );

    $validator = app(RequiredInputFieldsValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toBeArray()->toHaveCount(0);
});

it('returns a missing issue when kyc is declared but absent', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['kyc'],
        ],
    ]));

    $validator = app(RequiredInputFieldsValidator::class);

    $issues = $validator->validate($voucher, new RedemptionEvidenceData());

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('kyc')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::REQUIRED_INPUT_MISSING);
});