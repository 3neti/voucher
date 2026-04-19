<?php

use LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract;

beforeEach(function () {
    $this->setupSystemUser();
});

afterEach(function () {
    \Illuminate\Support\Facades\Date::setTestNow();
});

function attachRedeemerToVoucher($voucher, \LBHurtado\Contact\Models\Contact $contact, array $redemption = []): void
{
    $payload = array_replace_recursive(makeRedeemPayload(), $redemption);

    if (
        isset($payload['location']) &&
        is_array($payload['location']) &&
        array_key_exists('latitude', $payload['location']) &&
        array_key_exists('longitude', $payload['location'])
    ) {
        $payload['location'] = [
            'lat' => $payload['location']['latitude'],
            'lng' => $payload['location']['longitude'],
        ];
    }

    $voucher->redeemers()->forceCreate([
        'redeemer_id' => $contact->getKey(),
        'redeemer_type' => $contact::class,
        'metadata' => [
            'redemption' => $payload,
        ],
    ]);

    $voucher->refresh();
}

function runValidateRedemptionContract($voucher)
{
    $pipe = app(ValidateRedemptionContract::class);

    return $pipe->handle($voucher, fn ($passedVoucher) => $passedVoucher);
}

it('passes when required signature selfie and location are present', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'signature' => [
                'required' => true,
                'on_failure' => 'block',
            ],
            'selfie' => [
                'required' => true,
                'on_failure' => 'block',
            ],
            'location' => [
                'required' => true,
                'target_lat' => 14.5995,
                'target_lng' => 120.9842,
                'radius_meters' => 500,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact, [
        'signature' => 'data:image/png;base64,FAKE_SIGNATURE',
        'selfie' => 'data:image/jpeg;base64,FAKE_SELFIE',
        'location' => [
            'lat' => 14.5996,
            'lng' => 120.9843,
        ],
    ]);

    $result = runValidateRedemptionContract($voucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('blocks when redemption is outside the allowed time window', function () {
    \Illuminate\Support\Facades\Date::setTestNow(
        now()->setTime(23, 30, 0)
    );

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

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.passed'))->toBeFalse()
        ->and(data_get($voucher->metadata, 'redemption_validation.violations.time'))->toBe('outside_time_window');
});

it('passes when redemption is inside the allowed time window', function () {
    \Illuminate\Support\Facades\Date::setTestNow(
        now()->setTime(10, 0, 0)
    );

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

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    $result = runValidateRedemptionContract($voucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('blocks when redemption exceeds the configured time limit', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'starts_at' => now()->subMinutes(45)->toIso8601String(),
        'validation' => [
            'time' => [
                'limit_minutes' => 30,
                'track_duration' => true,
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact, [
        'redeemed_at' => now()->toIso8601String(),
    ]);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.passed'))->toBeFalse()
        ->and(data_get($voucher->metadata, 'redemption_validation.violations.time'))->toBe('time_limit_exceeded');
});

it('blocks when required signature is missing', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'signature' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.passed'))->toBeFalse()
        ->and(data_get($voucher->metadata, 'redemption_validation.violations.signature'))->toBe('missing');
});

it('blocks when required selfie is missing', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'selfie' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.passed'))->toBeFalse()
        ->and(data_get($voucher->metadata, 'redemption_validation.violations.selfie'))->toBe('missing');
});

it('blocks when required location is missing', function () {
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

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.passed'))->toBeFalse()
        ->and(data_get($voucher->metadata, 'redemption_validation.violations.location'))->toBe('missing');
});

it('blocks when required location is outside the allowed radius', function () {
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

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact, [
        'location' => [
            'lat' => 14.6095,
            'lng' => 120.9942,
        ],
    ]);

    $redeemerRecord = $voucher->redeemers()->latest('id')->first();

    expect(data_get($redeemerRecord->metadata, 'redemption.location.lat'))->not->toBeNull()
        ->and(data_get($redeemerRecord->metadata, 'redemption.location.lng'))->not->toBeNull();

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.passed'))->toBeFalse()
        ->and(data_get($voucher->metadata, 'redemption_validation.violations.location'))->toBe('outside_radius');
});

it('allows redemption to continue when on_failure is warn', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'signature' => [
                'required' => true,
                'on_failure' => 'warn',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    $result = runValidateRedemptionContract($voucher);

    $voucher->refresh();

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->metadata, 'redemption_validation.passed'))->toBeFalse()
        ->and(data_get($voucher->metadata, 'redemption_validation.violations.signature'))->toBe('missing');
});

it('collects multiple contract violations in one failure', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'signature' => [
                'required' => true,
                'on_failure' => 'block',
            ],
            'selfie' => [
                'required' => true,
                'on_failure' => 'block',
            ],
            'location' => [
                'required' => true,
                'target_lat' => 14.5995,
                'target_lng' => 120.9842,
                'radius_meters' => 100,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    try {
        runValidateRedemptionContract($voucher);
        $this->fail('Expected VoucherRedemptionContractViolationException was not thrown.');
    } catch (VoucherRedemptionContractViolationException $e) {
        expect($e->violations)->toMatchArray([
            'signature' => 'missing',
            'selfie' => 'missing',
            'location' => 'missing',
        ]);
    }

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.violations'))->toMatchArray([
        'signature' => 'missing',
        'selfie' => 'missing',
        'location' => 'missing',
    ]);
});

it('does nothing when no validation contract is defined', function () {
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    $result = runValidateRedemptionContract($voucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('blocks when required otp is not verified', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact, [
        'otp' => [
            'verified' => false,
        ],
    ]);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.passed'))->toBeFalse()
        ->and(data_get($voucher->metadata, 'redemption_validation.violations.otp'))->toBe('otp_not_verified');
});

it('passes when required otp is verified', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact, [
        'otp' => [
            'verified' => true,
            'verified_at' => now()->toIso8601String(),
        ],
    ]);

    $result = runValidateRedemptionContract($voucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('blocks when required signature input is declared but missing', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['signature'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.passed'))->toBeFalse()
        ->and(data_get($voucher->metadata, 'redemption_validation.violations.signature'))->toBe('required_input_missing');
});

it('blocks when required selfie input is declared but missing', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['selfie'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.violations.selfie'))->toBe('required_input_missing');
});

it('blocks when required otp input is declared but missing', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.violations.otp'))->toBe('required_input_missing');
});

it('blocks when required location input is declared but missing', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['location'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.violations.location'))->toBe('required_input_missing');
});

it('passes when all declared required inputs are present', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['signature', 'selfie', 'otp', 'location'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact, [
        'signature' => 'data:image/png;base64,FAKE_SIGNATURE',
        'selfie' => 'data:image/jpeg;base64,FAKE_SELFIE',
        'otp' => [
            'value' => '123456',
        ],
        'location' => [
            'lat' => 14.5995,
            'lng' => 120.9842,
        ],
    ]);

    $result = runValidateRedemptionContract($voucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('blocks when kyc input is declared but absent', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['kyc'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.violations.kyc'))->toBe('required_input_missing');
});

it('passes when kyc input is declared and kyc evidence is present', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['kyc'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact, [
        'kyc' => [
            'face_verification' => [
                'verified' => true,
                'face_match' => true,
                'match_confidence' => 0.93,
                'verified_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    $result = runValidateRedemptionContract($voucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('treats inputs fields as presence checks and validation otp as semantic verification', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp'],
        ],
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact, [
        'otp' => [
            'value' => '123456',
            'verified' => false,
        ],
    ]);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.violations.otp'))->toBe('otp_not_verified');
});

it('treats selfie as presence contract and face match as semantic verification', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['selfie', 'kyc'],
        ],
        'validation' => [
            'face_match' => [
                'required' => true,
                'on_failure' => 'block',
                'min_confidence' => 0.90,
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachRedeemerToVoucher($voucher, $contact, [
        'selfie' => 'data:image/jpeg;base64,FAKE_SELFIE',
        'kyc' => [
            'face_verification' => [
                'verified' => false,
                'face_match' => false,
                'match_confidence' => 0.20,
                'verified_at' => now()->toIso8601String(),
            ],
        ],
    ]);

    expect(fn () => runValidateRedemptionContract($voucher))
        ->toThrow(VoucherRedemptionContractViolationException::class);

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'redemption_validation.violations.face_match'))->toBe('face_match_not_verified');
});

function attachInputsRedeemer(\LBHurtado\Voucher\Models\Voucher $voucher, \LBHurtado\Contact\Models\Contact $contact, array $inputs): void
{
    $voucher->redeemers()->forceCreate([
        'redeemer_id' => $contact->getKey(),
        'redeemer_type' => $contact::class,
        'metadata' => [
            'inputs' => $inputs,
        ],
    ]);

    $voucher->refresh();
}

it('passes when required signature is supplied via inputs.signature', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['signature'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'signature' => 'data:image/png;base64,FAKE_SIGNATURE',
    ]);

    $voucher->refresh();

    $pipe = app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class);

    $result = $pipe->handle($voucher, fn ($passedVoucher) => $passedVoucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('passes when required selfie is supplied via inputs.selfie', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['selfie'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'selfie' => 'data:image/jpeg;base64,FAKE_SELFIE',
    ]);

    $voucher->refresh();

    $pipe = app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class);

    $result = $pipe->handle($voucher, fn ($passedVoucher) => $passedVoucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('passes when required location is supplied via inputs.location', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['location'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'location' => [
            'lat' => 14.5995,
            'lng' => 121.0288,
        ],
    ]);

    $voucher->refresh();

    $pipe = app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class);

    $result = $pipe->handle($voucher, fn ($passedVoucher) => $passedVoucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('passes when required otp is supplied via inputs.otp', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'otp' => '123456',
    ]);

    $voucher->refresh();

    $pipe = app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class);

    $result = $pipe->handle($voucher, fn ($passedVoucher) => $passedVoucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('passes when required kyc is supplied via inputs.kyc', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['kyc'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'kyc' => [
            'face_verification' => [
                'verified' => true,
                'face_match' => true,
                'match_confidence' => 0.95,
            ],
        ],
    ]);

    $voucher->refresh();

    $pipe = app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class);

    $result = $pipe->handle($voucher, fn ($passedVoucher) => $passedVoucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('passes when multiple required inputs are supplied via inputs only', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['name', 'email', 'birth_date', 'otp', 'signature', 'selfie', 'location', 'kyc'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'birth_date' => '1990-01-01',
        'otp' => '123456',
        'signature' => 'data:image/png;base64,FAKE_SIGNATURE',
        'selfie' => 'data:image/jpeg;base64,FAKE_SELFIE',
        'location' => [
            'lat' => 14.5995,
            'lng' => 121.0288,
        ],
        'kyc' => [
            'face_verification' => [
                'verified' => true,
                'face_match' => true,
                'match_confidence' => 0.95,
            ],
        ],
    ]);

    $voucher->refresh();

    $pipe = app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class);

    $result = $pipe->handle($voucher, fn ($passedVoucher) => $passedVoucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('blocks when inputs.signature is an empty string', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['signature'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'signature' => '',
    ]);

    $voucher->refresh();

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.signature'))
        ->toBe('required_input_missing');
});

it('blocks when inputs.selfie is an empty string', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['selfie'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'selfie' => '',
    ]);

    $voucher->refresh();

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.selfie'))
        ->toBe('required_input_missing');
});

it('blocks when inputs.location has latitude but no longitude', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['location'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'location' => [
            'lat' => 14.5995,
        ],
    ]);

    $voucher->refresh();

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.location'))
        ->toBe('required_input_missing');
});

it('blocks when inputs.location has longitude but no latitude', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['location'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'location' => [
            'lng' => 121.0288,
        ],
    ]);

    $voucher->refresh();

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.location'))
        ->toBe('required_input_missing');
});

it('blocks when inputs.kyc is an empty array', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['kyc'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'location' => [
            'kyc' => [],
        ],
    ]);

    $voucher->refresh();

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.kyc'))
        ->toBe('required_input_missing');
});

it('blocks when inputs.otp is an empty string', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'location' => [
            'otp' => '',
        ],
    ]);

    $voucher->refresh();

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.otp'))
        ->toBe('required_input_missing');
});

it('blocks when multiple inputs-only required fields are blank or incomplete', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['signature', 'selfie', 'otp', 'location', 'kyc'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'location' => [
            'signature' => '',
            'selfie' => '',
            'otp' => '',
            'location' => [
                'lat' => 14.5995,
            ],
            'kyc' => [],
        ],
    ]);

    $voucher->refresh();

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.signature'))->toBe('required_input_missing')
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.selfie'))->toBe('required_input_missing')
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.otp'))->toBe('required_input_missing')
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.location'))->toBe('required_input_missing')
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.kyc'))->toBe('required_input_missing');
});

it('blocks when inputs.otp is present but not verified and otp validation is required', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp'],
        ],
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'otp' => '123456',
        'otp_verified' => false,
    ]);

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.otp'))
        ->toBe('otp_not_verified');
});

it('blocks when inputs.location is present but outside the allowed radius', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['location'],
        ],
        'validation' => [
            'location' => [
                'required' => true,
                'target_lat' => 14.646954526919632,
                'target_lng' => 121.0288619903668,
                'radius_meters' => 50,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'location' => [
            'lat' => 14.5995,
            'lng' => 121.0288,
        ],
    ]);

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.location'))
        ->toBe('outside_radius');
});

it('blocks when inputs.kyc is present but face verification is not verified', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['kyc'],
        ],
        'validation' => [
            'face_match' => [
                'required' => true,
                'on_failure' => 'block',
                'min_confidence' => 0.90,
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'kyc' => [
            'face_verification' => [
                'verified' => false,
                'face_match' => false,
                'match_confidence' => 0.20,
            ],
        ],
    ]);

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.face_match'))
        ->toBe('face_match_not_verified');
});

it('blocks when inputs.kyc is present but face match confidence is too low', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['kyc'],
        ],
        'validation' => [
            'face_match' => [
                'required' => true,
                'on_failure' => 'block',
                'min_confidence' => 0.95,
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'kyc' => [
            'face_verification' => [
                'verified' => true,
                'face_match' => true,
                'match_confidence' => 0.80,
            ],
        ],
    ]);

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.face_match'))
        ->toBe('face_match_confidence_too_low');
});

it('allows inputs.location outside radius to continue when on_failure is warn', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['location'],
        ],
        'validation' => [
            'location' => [
                'required' => true,
                'target_lat' => 14.646954526919632,
                'target_lng' => 121.0288619903668,
                'radius_meters' => 50,
                'on_failure' => 'warn',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'location' => [
            'lat' => 14.5995,
            'lng' => 121.0288,
        ],
    ]);

    $result = app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation.should_block'))->toBeFalse()
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.location'))->toBe('outside_radius');
});

it('collects multiple semantic issues from inputs only payloads', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp', 'location', 'kyc'],
        ],
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
            'location' => [
                'required' => true,
                'target_lat' => 14.646954526919632,
                'target_lng' => 121.0288619903668,
                'radius_meters' => 50,
                'on_failure' => 'block',
            ],
            'face_match' => [
                'required' => true,
                'on_failure' => 'block',
                'min_confidence' => 0.90,
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'otp' => '123456',
        'otp_verified' => false,
        'location' => [
            'lat' => 14.5995,
            'lng' => 121.0288,
        ],
        'kyc' => [
            'face_verification' => [
                'verified' => false,
                'face_match' => false,
                'match_confidence' => 0.20,
            ],
        ],
    ]);

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.otp'))->toBe('otp_not_verified')
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.location'))->toBe('outside_radius')
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.face_match'))->toBe('face_match_not_verified');
});

it('passes when otp validation is required and form-flow otp step has verified_at', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp'],
        ],
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'otp' => [
            'otp_code' => '123456',
            'verified_at' => now()->toIso8601String(),
            'reference_id' => 'flow-abc123',
        ],
    ]);

    $result = app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('blocks when otp validation is required and form-flow otp step has explicit verified false', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp'],
        ],
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'otp' => [
            'otp_code' => '123456',
            'verified' => false,
            'reference_id' => 'flow-abc123',
        ],
    ]);

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.otp'))
        ->toBe('otp_not_verified');
});

it('blocks when otp validation is required and form-flow otp step has otp_code without verification metadata', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp'],
        ],
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'otp' => [
            'otp_code' => '123456',
            'reference_id' => 'flow-abc123',
        ],
    ]);

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.otp'))
        ->toBe('otp_not_verified');
});

it('passes when otp is required as input and form-flow otp step provides otp_code', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp'],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'otp' => [
            'otp_code' => '123456',
            'reference_id' => 'flow-abc123',
        ],
    ]);

    $result = app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('passes when otp is required as input and flat otp_code plus verified_at are supplied', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp'],
        ],
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'otp_code' => '123456',
        'verified_at' => now()->toIso8601String(),
        'reference_id' => 'flow-flat-otp',
    ]);

    $result = app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code)
        ->and(data_get($voucher->fresh()->metadata, 'redemption_validation'))->toBeNull();
});

it('prefers explicit verified false over verified_at inference in form-flow otp step payload', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => [
            'fields' => ['otp'],
        ],
        'validation' => [
            'otp' => [
                'required' => true,
                'on_failure' => 'block',
            ],
        ],
    ]));

    $contact = makeContactForRedemption();

    attachInputsRedeemer($voucher, $contact, [
        'otp' => [
            'otp_code' => '123456',
            'verified' => false,
            'verified_at' => now()->toIso8601String(),
            'reference_id' => 'flow-abc123',
        ],
    ]);

    expect(fn () => app(\LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract::class)
        ->handle($voucher, fn ($passedVoucher) => $passedVoucher))
        ->toThrow(\LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException::class);

    expect(data_get($voucher->fresh()->metadata, 'redemption_validation.violations.otp'))
        ->toBe('otp_not_verified');
});