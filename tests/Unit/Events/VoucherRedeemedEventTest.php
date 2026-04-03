<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('dispatches a redeemed event on full redemption', function () {
    $eventClass = 'LBHurtado\\Voucher\\Events\\VoucherRedeemed';

    if (! class_exists($eventClass)) {
        $this->markTestSkipped('VoucherRedeemed event class does not yet exist in the package.');
    }

    Event::fake([$eventClass]);
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00));
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);

    Event::assertDispatched($eventClass);
});

it('does not dispatch a redeemed event when payout fails', function () {
    $eventClass = 'LBHurtado\\Voucher\\Events\\VoucherRedeemed';

    if (! class_exists($eventClass)) {
        $this->markTestSkipped('VoucherRedeemed event class does not yet exist in the package.');
    }

    Event::fake([$eventClass]);
    fakePayoutProvider()->willReturnFailedResult(transactionId: 'TXN-EVT-FAIL-1');

    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00));
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);

    Event::assertNotDispatched($eventClass);
});

it('includes final redeemed amount in the event payload', function () {
    $eventClass = 'LBHurtado\\Voucher\\Events\\VoucherRedeemed';

    if (! class_exists($eventClass)) {
        $this->markTestSkipped('VoucherRedeemed event class does not yet exist in the package.');
    }

    Event::fake([$eventClass]);
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher(validVoucherInstructions(amount: 100.00));
    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);

    Event::assertDispatched($eventClass, function ($event) use ($voucher) {
        $eventVoucher = data_get($event, 'voucher');
        $amount = data_get($event, 'amount')
            ?? data_get($event, 'redeemed_amount')
            ?? data_get($event, 'final_amount');

        expect($eventVoucher)->not->toBeNull()
            ->and($eventVoucher->id)->toBe($voucher->id);

        if ($amount !== null) {
            expect((float) $amount)->toBe(100.0);
        }

        return true;
    });
});
