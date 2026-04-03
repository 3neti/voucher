<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('preserves feedback instructions so the host app can dispatch feedbacks after successful redemption', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'feedback' => [
            'email' => 'issuer@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://example.test/hook',
        ],
    ]));

    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata['instructions']['feedback'])->toMatchArray([
        'email' => 'issuer@example.com',
        'mobile' => '09171234567',
        'webhook' => 'https://example.test/hook',
    ]);
});

it('does not require any package-level notification binding for redemption to succeed', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(overrides: [
        'feedback' => [
            'email' => 'issuer@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://example.test/hook',
        ],
    ]));

    $result = RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and($voucher->redeemed_at)->not->toBeNull();
});

it('uses stored feedback configuration rather than hard-coding any specific notification channel in the package pipeline', function () {
    $postRedemption = config('voucher-pipeline.post-redemption', []);

    expect($postRedemption)->not->toContain('App\\Pipelines\\RedeemedVoucher\\SendFeedbacks')
        ->and($postRedemption)->not->toContain('LBHurtado\\Voucher\\Actions\\SendFeedbacks');
});
