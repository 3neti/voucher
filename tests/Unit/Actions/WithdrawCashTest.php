<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Wallet\Actions\WithdrawCash;
use Bavix\Wallet\Models\Transaction;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('withdraws cash only after a successful payout result', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00));
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
    $voucher->refresh();

    expect($voucher->getRedeemedTotal())->toBe(100.0)
        ->and($voucher->getRemainingBalance())->toBe(0.0);
});

it('withdraws exactly the requested amount when using the wallet withdrawal action directly', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 300.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'cash' => [
                'slice_mode' => 'open',
                'min_withdrawal' => 50,
            ],
        ],
    ));

    $cash = $voucher->cash;

    $transaction = WithdrawCash::run(
        $cash,
        null,
        null,
        [
            'flow' => 'redeem',
            'voucher_code' => $voucher->code,
        ],
        10000 // 100.00 in minor units
    );

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and(abs($transaction->amount))->toBe(10000)
        ->and((float) $cash->wallet->fresh()->balanceFloat)->toBe(200.00);
});

it('prevents duplicate withdrawal on retry through the package redemption entrypoint', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00));
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue()
        ->and(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();

    $voucher->refresh();

    expect($voucher->getRedeemedTotal())->toBe(100.0)
        ->and($voucher->getRemainingBalance())->toBe(0.0);
});

it('surfaces wallet debit failures when direct withdrawal exceeds available balance', function () {
    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00));

    expect(fn () => WithdrawCash::run(
        $voucher->cash,
        null,
        null,
        [
            'flow' => 'redeem',
            'voucher_code' => $voucher->code,
        ],
        20000 // 200.00 > 100.00 available
    ))->toThrow(InvalidArgumentException::class);
});
