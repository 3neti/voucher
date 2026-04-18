<?php

use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Validators\SignatureRuleValidator;

beforeEach(function () {
    $this->setupSystemUser();
});

it('supports vouchers that require signature validation', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'signature' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $validator = app(SignatureRuleValidator::class);

    expect($validator->supports($voucher))->toBeTrue();
});

it('does not support vouchers that do not require signature validation', function () {
    $voucher = issueVoucher();

    $validator = app(SignatureRuleValidator::class);

    expect($validator->supports($voucher))->toBeFalse();
});

it('returns no issues when signature is present', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'signature' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        signature: 'data:image/png;base64,FAKE_SIGNATURE',
    );

    $validator = app(SignatureRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toBeArray()->toHaveCount(0);
});

it('returns a block issue when signature is required but missing', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'signature' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        signature: null,
    );

    $validator = app(SignatureRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('signature')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::MISSING)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::BLOCK);
});

it('returns a warn issue when signature failure mode is warn', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'signature' => [
                'required' => true,
                'on_failure' => 'warn',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        signature: '',
    );

    $validator = app(SignatureRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('signature')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::MISSING)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::WARN);
});