<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Events\DisburseInputPrepared;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('dispatches the disbursement requested event during redemption', function () {
    Event::fake([DisburseInputPrepared::class]);
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

    Event::assertDispatched(DisburseInputPrepared::class);
});

it('includes the voucher and payout request context in the event payload', function () {
    Event::fake([DisburseInputPrepared::class]);
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

    Event::assertDispatched(DisburseInputPrepared::class, function ($event) use ($voucher) {
        $eventVoucher = data_get($event, 'voucher');
        $input = data_get($event, 'input') ?? data_get($event, 'request') ?? data_get($event, 'payoutRequest');

        expect($eventVoucher)->not->toBeNull()
            ->and($eventVoucher->id)->toBe($voucher->id);

        expect($input)->not->toBeNull()
            ->and((float) $input->amount)->toBe(100.0)
            ->and($input->bank_code)->toBe('GCASH')
            ->and($input->account_number)->toBe('09171234567')
            ->and($input->settlement_rail)->toBe('INSTAPAY');

        return true;
    });
});
