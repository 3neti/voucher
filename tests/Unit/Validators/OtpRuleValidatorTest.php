<?php

use Illuminate\Support\Carbon;
use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Validators\OtpRuleValidator;

beforeEach(function () {
    $this->setupSystemUser();
});

it('supports vouchers that require otp validation', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $validator = app(OtpRuleValidator::class);

    expect($validator->supports($voucher))->toBeTrue();
});

it('does not support vouchers that do not require otp validation', function () {
    $voucher = issueVoucher();

    $validator = app(OtpRuleValidator::class);

    expect($validator->supports($voucher))->toBeFalse();
});

it('returns no issues when otp is verified', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        otp_verified: true,
        otp_verified_at: Carbon::now(),
    );

    $validator = app(OtpRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toBeArray()->toHaveCount(0);
});

it('returns a block issue when otp is required but not verified', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        otp_verified: false,
    );

    $validator = app(OtpRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('otp')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::OTP_NOT_VERIFIED)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::BLOCK);
});

it('returns a warn issue when otp failure mode is warn', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'warn',
            ],
        ],
    ]));

    $evidence = new RedemptionEvidenceData(
        otp_verified: null,
    );

    $validator = app(OtpRuleValidator::class);

    $issues = $validator->validate($voucher, $evidence);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->field)->toBe('otp')
        ->and($issues[0]->code)->toBe(RedemptionValidationCode::OTP_NOT_VERIFIED)
        ->and($issues[0]->severity)->toBe(RedemptionValidationSeverity::WARN);
});