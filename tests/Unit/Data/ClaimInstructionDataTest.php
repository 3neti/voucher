<?php

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Data\ClaimInstructionData;
use LBHurtado\Voucher\Data\ClaimOutcomeInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

it('hydrates and serializes typed one-of claim instructions', function () {
    $instructions = VoucherInstructionsData::createFromAttribs([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
        'claim' => [
            'outcomes' => [
                [
                    'key' => 'provider_disbursement',
                    'pricing_profile' => 'instapay-disbursement-v1',
                ],
                [
                    'key' => 'account_funding',
                    'pricing_profile' => 'account-funding-v1',
                ],
            ],
            'selection' => 'claimant',
            'consumption' => 'one_of',
            'default_outcome' => 'provider_disbursement',
            'onboarding' => ['mode' => 'if_required'],
        ],
    ]);

    expect($instructions->claim)->toBeInstanceOf(ClaimInstructionData::class)
        ->and($instructions->claim->outcomes)->toHaveCount(2)
        ->and($instructions->claim->outcomes[0])
        ->toBeInstanceOf(ClaimOutcomeInstructionData::class)
        ->and($instructions->claim->outcomes[1]->key)->toBe('account_funding')
        ->and(data_get($instructions->toCleanArray(), 'claim.profile'))
        ->toBe('voucher.claim.v1')
        ->and(data_get($instructions->toCleanArray(), 'claim.consumption'))
        ->toBe('one_of');
});

it('omits claim instructions from legacy payloads', function () {
    $instructions = VoucherInstructionsData::createFromAttribs([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
    ]);

    expect($instructions->claim)->toBeNull()
        ->and($instructions->toCleanArray())->not->toHaveKey('claim');
});

it('rejects an undeclared default claim outcome', function () {
    ClaimInstructionData::from([
        'outcomes' => [
            ['key' => 'account_funding'],
        ],
        'default_outcome' => 'provider_disbursement',
    ]);
})->throws(
    InvalidArgumentException::class,
    'The default Voucher claim outcome must be one of the declared outcomes.',
);

it('rejects malformed claim outcome keys during instruction validation', function () {
    VoucherInstructionsData::createFromAttribs([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
        'claim' => [
            'outcomes' => [
                ['key' => 'Account Funding'],
            ],
        ],
    ]);
})->throws(ValidationException::class);
