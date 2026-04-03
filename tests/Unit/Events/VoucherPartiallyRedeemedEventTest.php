<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('dispatches a dedicated partially redeemed event when a divisible voucher is partially redeemed', function () {
    $eventClass = 'LBHurtado\\Voucher\\Events\\VoucherPartiallyRedeemed';

    if (! class_exists($eventClass)) {
        $this->markTestSkipped('VoucherPartiallyRedeemed event class does not yet exist in the package.');
    }

    Event::fake([$eventClass]);
    fakePayoutProvider()->willReturnSuccessfulResult();

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

    RedeemVoucher::run(makeContactForRedemption(), $voucher->code, [
        'inputs' => [
            'requested_amount' => 100.00,
        ],
    ]);

    Event::assertDispatched($eventClass);
});

it('includes voucher identity and redeemed amount in the event payload', function () {
    $eventClass = 'LBHurtado\\Voucher\\Events\\VoucherPartiallyRedeemed';

    if (! class_exists($eventClass)) {
        $this->markTestSkipped('VoucherPartiallyRedeemed event class does not yet exist in the package.');
    }

    Event::fake([$eventClass]);
    fakePayoutProvider()->willReturnSuccessfulResult();

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

    RedeemVoucher::run(makeContactForRedemption(), $voucher->code, [
        'inputs' => [
            'requested_amount' => 100.00,
        ],
    ]);

    Event::assertDispatched($eventClass, function ($event) use ($voucher) {
        $amount = data_get($event, 'amount')
            ?? data_get($event, 'redeemed_amount')
            ?? data_get($event, 'requested_amount');

        $eventVoucher = data_get($event, 'voucher');

        expect($eventVoucher)->not->toBeNull();
        expect($eventVoucher->id)->toBe($voucher->id);
        expect((float) $amount)->toBe(100.0);

        return true;
    });
});
