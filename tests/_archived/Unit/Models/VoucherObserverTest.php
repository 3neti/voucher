<?php

use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Handlers\HandleRedeemedVoucher;
use LBHurtado\Voucher\Handlers\HandleRedeemingVoucher;
use LBHurtado\Voucher\Handlers\HandleShouldMarkRedeemedVoucher;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

it('invokes all three redemption handlers via the observer events', function () {
    // 1) Create a user/contact and a voucher
    $contact = Contact::factory()->create();
    $created = Vouchers::create(1);               // however many you need
    $voucher = is_array($created) ? collect($created)->first() : $created;

    // 2) Make spies for each of your handlers, and bind them into the container
    $spyRedeeming = Mockery::spy(HandleRedeemingVoucher::class);
    $spyShouldMark = Mockery::spy(HandleShouldMarkRedeemedVoucher::class);
    $spyRedeemed = Mockery::spy(HandleRedeemedVoucher::class);

    $this->app->instance(HandleRedeemingVoucher::class, $spyRedeeming);
    $this->app->instance(HandleShouldMarkRedeemedVoucher::class, $spyShouldMark);
    $this->app->instance(HandleRedeemedVoucher::class, $spyRedeemed);

    // 3) Now call the facade. This should trigger, in order:
    //    - voucher::redeeming     → HandleRedeemingVoucher
    //    - voucher::shouldMarkRedeemed → HandleShouldMarkRedeemedVoucher
    //    - voucher::redeemed      → HandleRedeemedVoucher
    Vouchers::redeem($voucher->code, $contact, ['foo' => 'bar']);

    // 4) Assert each handler was called exactly once with our Voucher
    $spyRedeeming
        ->shouldHaveReceived('handle')
        ->once()
        ->withArgs(fn ($arg) => $arg instanceof Voucher && $arg->is($voucher));

    $spyShouldMark
        ->shouldHaveReceived('handle')
        ->once()
        ->withArgs(fn ($arg) => $arg instanceof Voucher && $arg->is($voucher));

    $spyRedeemed
        ->shouldHaveReceived('handle')
        ->once()
        ->withArgs(fn ($arg) => $arg instanceof Voucher && $arg->is($voucher));
});
