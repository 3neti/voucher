<?php

use LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedeemerAndCash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('accepts a voucher that has both redeemer contact and cash entity', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    $pipe = app(ValidateRedeemerAndCash::class);

    $result = $pipe->handle($voucher, fn ($passedVoucher) => $passedVoucher);

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe($voucher->code);
});

it('rejects a voucher when the redeemer contact is missing', function () {
    $voucher = issueVoucher();
    $pipe = app(ValidateRedeemerAndCash::class);

    $result = $pipe->handle($voucher, fn () => 'passed');

    expect($result)->toBeNull();
});

it('rejects a voucher when the cash entity is missing', function () {
    $voucher = issueVoucher();
    $voucher->voucherEntities()->delete();
    $voucher->refresh();

    $contact = makeContactForRedemption();
    $voucher->redeemers()->forceCreate([
        'redeemer_id' => $contact->getKey(),
        'redeemer_type' => $contact::class,
        'metadata' => ['redemption' => makeRedeemPayload()],
    ]);
    $voucher->refresh();

    $pipe = app(ValidateRedeemerAndCash::class);

    $result = $pipe->handle($voucher, fn () => 'passed');

    expect($result)->toBeNull();
});
