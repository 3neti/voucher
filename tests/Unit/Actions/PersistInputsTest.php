<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('persists collected redemption inputs onto the redeemer metadata attached to the voucher', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();
    $payload = makeRedeemPayload([
        'bank_account' => 'GCASH:09179990000',
        'requested_amount' => 75.50,
        'secret' => '1234',
    ]);

    RedeemVoucher::run($contact, $voucher->code, $payload);
    $voucher->refresh();

    $redeemer = $voucher->redeemers()->first();

    expect($redeemer)->not->toBeNull()
        ->and($redeemer->metadata)->toHaveKey('redemption')
        ->and($redeemer->metadata['redemption']['requested_amount'])->toBe(75.50)
        ->and($redeemer->metadata['redemption']['secret'])->toBe('1234')
        ->and($redeemer->metadata['redemption']['bank_account'])->toBe('GCASH:09179990000');
});

it('persists the redemption payload as supplied rather than silently filtering fields', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();
    $payload = makeRedeemPayload([
        'bank_account' => 'GCASH:09170000001',
        'custom_note' => 'keep-this',
        'requested_amount' => 20,
    ]);

    RedeemVoucher::run($contact, $voucher->code, $payload);
    $voucher->refresh();
    $redeemer = $voucher->redeemers()->first();

    expect($redeemer)->not->toBeNull()
        ->and($redeemer->metadata['redemption']['custom_note'])->toBe('keep-this')
        ->and($redeemer->metadata['redemption']['requested_amount'])->toBe(20);
});

it('runs before the disbursement step by allowing redemption metadata to influence the payout request', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption([
        'bank_code' => 'BPI',
        'account_number' => '0000000001',
    ]);

    RedeemVoucher::run($contact, $voucher->code, [
        'bank_account' => 'GCASH:09175551234',
    ]);

    $request = test()->fakePayoutProvider()->lastRequest;

    expect($request)->not->toBeNull()
        ->and($request->bank_code)->toBe('GCASH')
        ->and($request->account_number)->toBe('09175551234');
});

it('is deterministic when run multiple times with the same payload on separate vouchers', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $payload = makeRedeemPayload([
        'bank_account' => 'GCASH:09178889999',
        'requested_amount' => 50,
    ]);

    $first = issueVoucher();
    $second = issueVoucher();
    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $first->code, $payload);
    RedeemVoucher::run($contact, $second->code, $payload);

    $first->refresh();
    $second->refresh();
    $firstRedeemer = $first->redeemers()->first();
    $secondRedeemer = $second->redeemers()->first();

    expect($firstRedeemer)->not->toBeNull()
        ->and($secondRedeemer)->not->toBeNull()
        ->and($firstRedeemer->metadata['redemption'])->toMatchArray($secondRedeemer->metadata['redemption']);
});
