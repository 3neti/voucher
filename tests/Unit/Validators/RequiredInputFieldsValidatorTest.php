<?php

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Services\RedemptionContractEngine;
use LBHurtado\Voucher\Specifications\InputsSpecification;
use LBHurtado\Voucher\Validators\RequiredInputFieldsValidator;

it('checks declared required document references and allows omitted optional documents', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: ['inputs' => [
        'fields' => ['documents'],
        'documents' => [
            ['key' => 'id', 'label' => 'Identity', 'accept' => ['application/pdf'], 'required' => true],
            ['key' => 'extra', 'label' => 'Extra', 'accept' => ['application/pdf'], 'required' => false],
        ],
    ]]));
    $engine = app(RedemptionContractEngine::class);
    expect($engine->validateInputs($voucher, ['documents' => []])->should_block)->toBeTrue()
        ->and($engine->validateInputs($voucher, ['documents' => [['key' => 'id', 'reference' => '01ARZ3NDEKTSV4RRFFQ69G5FAV']]])->should_block)->toBeFalse()
        ->and($engine->validateInputs($voucher, ['documents' => [['key' => 'id', 'reference' => 'invalid']]])->should_block)->toBeTrue();
    $guard = app(InputsSpecification::class);
    $missing = new RedemptionContext(mobile: '09173011987', inputs: []);
    $complete = new RedemptionContext(mobile: '09173011987', inputs: [
        'documents' => [['key' => 'id', 'reference' => '01ARZ3NDEKTSV4RRFFQ69G5FAV']],
    ]);
    expect($guard->passes($voucher, $missing))->toBeFalse()
        ->and($guard->getMissingFields($voucher, $missing))->toContain('documents')
        ->and($guard->passes($voucher, $complete))->toBeTrue();
});

it('does not require an upload when every requested document is optional', function (): void {
    $voucher = issueVoucher(validVoucherInstructions(overrides: ['inputs' => [
        'fields' => ['documents'],
        'documents' => [['key' => 'id', 'label' => 'Identity', 'accept' => ['application/pdf'], 'required' => false]],
    ]]));
    expect(app(RedemptionContractEngine::class)->validateInputs($voucher, ['documents' => []])->should_block)->toBeFalse();
    $context = new RedemptionContext(mobile: '09173011987', inputs: []);
    expect(app(InputsSpecification::class)->passes($voucher, $context))->toBeTrue();
});

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

    $issues = $validator->validate($voucher, new RedemptionEvidenceData);

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

    $issues = $validator->validate($voucher, new RedemptionEvidenceData);

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

    $issues = $validator->validate($voucher, new RedemptionEvidenceData);

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

    $issues = $validator->validate($voucher, new RedemptionEvidenceData);

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

    $issues = $validator->validate($voucher, new RedemptionEvidenceData);

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

    $issues = $validator->validate($voucher, new RedemptionEvidenceData);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('kyc')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::REQUIRED_INPUT_MISSING);
});
