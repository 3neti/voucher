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