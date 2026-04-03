<?php

use LBHurtado\Voucher\Tests\Fakes\FakePayoutProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('calls payout provider through the emi-core contract', function () {
    expect(app(PayoutProvider::class))->toBeInstanceOf(FakePayoutProvider::class);
});

it('calls disburse exactly once on the happy path', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $voucher->code);

    expect(test()->fakePayoutProvider()->disburseCallCount)->toBe(1);
});

it('never calls a concrete provider adapter directly from voucher', function () {
    expect(app(PayoutProvider::class))->toBeInstanceOf(FakePayoutProvider::class);
    expect(app(PayoutProvider::class))->not->toBeInstanceOf(\stdClass::class);
});

it('passes the built payout request to the provider unmodified', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();
    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00, settlementRail: 'INSTAPAY'));
    $contact = makeContactForRedemption([
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ]);

    RedeemVoucher::run($contact, $voucher->code);

    $request = test()->fakePayoutProvider()->lastRequest;

    expect($request)->not->toBeNull()
        ->and($request->reference)->toContain($voucher->code)
        ->and($request->external_code)->toBe($voucher->code)
        ->and($request->bank_code)->toBe('GCASH')
        ->and($request->account_number)->toBe('09171234567')
        ->and($request->amount)->toBe(100.0)
        ->and($request->settlement_rail)->toBe('INSTAPAY');
});

it('captures every payout request made during the test run', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $first = issueVoucher();
    $second = issueVoucher();
    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $first->code);
    RedeemVoucher::run($contact, $second->code);

    expect(test()->fakePayoutProvider()->requests)->toHaveCount(2)
        ->and(test()->fakePayoutProvider()->requests[0]->external_code)->toBe($first->code)
        ->and(test()->fakePayoutProvider()->requests[1]->external_code)->toBe($second->code);
});
