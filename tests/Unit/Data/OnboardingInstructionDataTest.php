<?php

use LBHurtado\Voucher\Data\VoucherInstructionsData;

function onboardingInstructionPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'ONBD',
        'mask' => '****',
    ], $overrides);
}

it('defaults onboarding to false without changing the legacy wire shape', function () {
    $instructions = VoucherInstructionsData::createFromAttribs(
        onboardingInstructionPayload(),
    );

    expect($instructions->onboarding)->toBeFalse()
        ->and($instructions->toCleanArray())->not->toHaveKey('onboarding');
});

it('hydrates and serializes an explicit onboarding instruction', function () {
    $instructions = VoucherInstructionsData::createFromAttribs(
        onboardingInstructionPayload(['onboarding' => true]),
    );

    expect($instructions->onboarding)->toBeTrue()
        ->and($instructions->toCleanArray()['onboarding'])->toBeTrue();
});

it('maps explicit legacy required onboarding to the canonical instruction', function () {
    $instructions = VoucherInstructionsData::createFromAttribs(
        onboardingInstructionPayload([
            'claim' => [
                'outcomes' => [['key' => 'provider_disbursement']],
                'onboarding' => ['mode' => 'required'],
            ],
        ]),
    );

    expect($instructions->onboarding)->toBeTrue()
        ->and($instructions->toCleanArray()['onboarding'])->toBeTrue();
});

it('does not reclassify legacy conditional provider provisioning as onboarding', function () {
    $instructions = VoucherInstructionsData::createFromAttribs(
        onboardingInstructionPayload([
            'claim' => [
                'outcomes' => [['key' => 'provider_disbursement']],
                'onboarding' => ['mode' => 'if_required'],
            ],
        ]),
    );

    expect($instructions->onboarding)->toBeFalse()
        ->and($instructions->toCleanArray())->not->toHaveKey('onboarding');
});

it('allows the canonical flag to override legacy compatibility metadata', function () {
    $instructions = VoucherInstructionsData::createFromAttribs(
        onboardingInstructionPayload([
            'onboarding' => false,
            'claim' => [
                'outcomes' => [['key' => 'provider_disbursement']],
                'onboarding' => ['mode' => 'required'],
            ],
        ]),
    );

    expect($instructions->onboarding)->toBeFalse()
        ->and($instructions->toCleanArray())->not->toHaveKey('onboarding');
});
