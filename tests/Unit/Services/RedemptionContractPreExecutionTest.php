<?php

use LBHurtado\Voucher\Services\RedemptionContractEngine;

beforeEach(function () {
    $this->setupSystemUser();
});

it('blocks a missing configured claim input before a redeemer record exists', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'inputs' => ['fields' => ['signature']],
    ]));

    $result = app(RedemptionContractEngine::class)->validateInputs($voucher, []);

    expect($result->should_block)->toBeTrue()
        ->and($result->issues)->toHaveCount(1)
        ->and($result->issues[0]->field)->toBe('signature')
        ->and($voucher->redeemers()->count())->toBe(0);
});

it('records a configured warning without blocking pre-execution', function () {
    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'validation' => [
            'selfie' => ['required' => true, 'on_failure' => 'warn'],
        ],
    ]));

    $result = app(RedemptionContractEngine::class)->validateInputs($voucher, []);

    expect($result->passed)->toBeFalse()
        ->and($result->should_block)->toBeFalse()
        ->and($result->issues[0]->field)->toBe('selfie');
});
