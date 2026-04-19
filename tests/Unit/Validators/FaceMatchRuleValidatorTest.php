<?php

use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Validators\FaceMatchRuleValidator;

beforeEach(function () {
    $this->setupSystemUser();
});

it('does not support vouchers when kyc is only required as an input field', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => [\LBHurtado\Voucher\Enums\VoucherInputField::KYC->value],
        ],
    ]));

    $validator = app(FaceMatchRuleValidator::class);

    expect(
        collect($voucher->instructions->inputs->fields)
            ->map(fn ($field) => $field instanceof \LBHurtado\Voucher\Enums\VoucherInputField ? $field->value : $field)
            ->contains('kyc')
    )->toBeTrue()
        ->and($validator->supports($voucher))->toBeFalse();
});

it('does not treat kyc input presence as face match validation requirement', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => [\LBHurtado\Voucher\Enums\VoucherInputField::KYC->value],
        ],
    ]));

    $validator = app(FaceMatchRuleValidator::class);

    expect($validator->supports($voucher))->toBeFalse();
});

it('supports vouchers with explicit face match validation', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'face_match' => [
                'required' => true,
                'on_failure' => 'block',
                'min_confidence' => 0.80,
            ],
        ],
    ]));

    $validator = app(FaceMatchRuleValidator::class);

    expect($validator->supports($voucher))->toBeTrue();
});

it('does not support vouchers without kyc or face match requirement', function () {
    $voucher = issueVoucher();

    $validator = app(FaceMatchRuleValidator::class);

    expect($validator->supports($voucher))->toBeFalse();
});

it('returns no issues when face verification and face match pass', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['kyc'],
        ],
        'validation' => [
            'face_match' => [
                'required' => true,
                'min_confidence' => 0.80,
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        face_verification_verified: true,
        face_match: true,
        match_confidence: 0.92,
        face_verified_at: now(),
    );

    $validator = app(FaceMatchRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toBeArray()->toHaveCount(0);
});

it('returns a not verified issue when face verification evidence is missing or failed', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['kyc'],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        face_verification_verified: false,
        face_match: false,
        match_confidence: 0.10,
    );

    $validator = app(FaceMatchRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('face_match')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::FACE_MATCH_NOT_VERIFIED)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::BLOCK);
});

it('returns a confidence too low issue when threshold is not met', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'face_match' => [
                'required' => true,
                'on_failure' => 'block',
                'min_confidence' => 0.90,
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        face_verification_verified: true,
        face_match: true,
        match_confidence: 0.82,
        face_verified_at: now(),
    );

    $validator = app(FaceMatchRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('face_match')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::FACE_MATCH_CONFIDENCE_TOO_LOW)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::BLOCK);
});

it('returns a warn issue when face match failure mode is warn', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'face_match' => [
                'required' => true,
                'on_failure' => 'warn',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        face_verification_verified: false,
        face_match: false,
    );

    $validator = app(FaceMatchRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('face_match')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::FACE_MATCH_NOT_VERIFIED)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::WARN);
});