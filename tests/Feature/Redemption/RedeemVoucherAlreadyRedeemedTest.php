<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('rejects redemption of an already redeemed voucher', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue()
        ->and(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();
});

it('does not duplicate side effects for an already redeemed voucher', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00));
    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $voucher->code);

    $afterFirstRequest = test()->fakePayoutProvider()->lastRequest;
    $afterFirstCount = test()->fakePayoutProvider()->disburseCallCount;

    RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect(test()->fakePayoutProvider()->disburseCallCount)->toBe($afterFirstCount)
        ->and(test()->fakePayoutProvider()->lastRequest?->reference)->toBe($afterFirstRequest?->reference)
        ->and($voucher->getRedeemedTotal())->toBe(100.0)
        ->and($voucher->getRemainingBalance())->toBe(0.0);
});
