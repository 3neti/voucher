<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('serializes payout request data consistently', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY'
    ));

    RedeemVoucher::run(
        makeContactForRedemption([
            'bank_code' => 'GCASH',
            'account_number' => '09171234567',
        ]),
        $voucher->code
    );

    $request = test()->fakePayoutProvider()->lastRequest;
    $payload = $request->toArray();

    expect($payload)->toMatchArray([
        'amount' => 100.0,
        'account_number' => '09171234567',
        'bank_code' => 'GCASH',
        'settlement_rail' => 'INSTAPAY',
        'external_id' => (string) $voucher->id,
        'external_code' => $voucher->code,
        'mobile' => '09171234567',
    ])
        ->and($payload['reference'])->toContain($voucher->code);
});

it('preserves expected keys across serialization', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);

    $payload = test()->fakePayoutProvider()->lastRequest->toArray();

    expect($payload)->toHaveKeys([
        'reference',
        'amount',
        'account_number',
        'bank_code',
        'settlement_rail',
        'external_id',
        'external_code',
        'user_id',
        'mobile',
    ]);
});

it('produces the same payout request for the same voucher state', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $firstVoucher = issueVoucher(validVoucherInstructions(amount: 100.00, settlementRail: 'INSTAPAY'));
    $secondVoucher = issueVoucher(validVoucherInstructions(amount: 100.00, settlementRail: 'INSTAPAY'));

    $contact = makeContactForRedemption([
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
    ]);

    RedeemVoucher::run($contact, $firstVoucher->code);
    $firstPayload = test()->fakePayoutProvider()->lastRequest->toArray();

    fakePayoutProvider()->willReturnSuccessfulResult();

    RedeemVoucher::run($contact, $secondVoucher->code);
    $secondPayload = test()->fakePayoutProvider()->lastRequest->toArray();

    expect($firstPayload['amount'])->toBe($secondPayload['amount'])
        ->and($firstPayload['account_number'])->toBe($secondPayload['account_number'])
        ->and($firstPayload['bank_code'])->toBe($secondPayload['bank_code'])
        ->and($firstPayload['settlement_rail'])->toBe($secondPayload['settlement_rail'])
        ->and($firstPayload['mobile'])->toBe($secondPayload['mobile']);
});
