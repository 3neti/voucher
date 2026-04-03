<?php

use FrittenKeeZ\Vouchers\Exceptions\VoucherAlreadyRedeemedException;
use FrittenKeeZ\Vouchers\Exceptions\VoucherNotFoundException;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\RedeemVoucher;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a dummy contact
    $this->contact = Contact::factory()->create([
        'mobile' => '09171234567',
        'country' => 'PH',
    ]);
});

it('redeems a voucher successfully', function () {
    // Arrange: mock the facade to return true
    Vouchers::shouldReceive('redeem')
        ->once()
        ->with('ABC123', $this->contact, ['redemption' => ['foo' => 'bar']])
        ->andReturnTrue();

    // Act
    $result = RedeemVoucher::run($this->contact, 'ABC123', ['foo' => 'bar']);

    // Assert
    expect($result)->toBeTrue();
});

it('returns false if voucher is not found', function () {
    // Arrange: redeem() throws VoucherNotFoundException
    Vouchers::shouldReceive('redeem')
        ->once()
        ->with('MISSING', $this->contact, ['redemption' => []])
        ->andThrow(new VoucherNotFoundException('Not found'));

    // Act
    $result = RedeemVoucher::run($this->contact, 'MISSING');

    // Assert
    expect($result)->toBeFalse();
});

it('returns false if voucher has already been redeemed', function () {
    // Arrange: redeem() throws VoucherAlreadyRedeemedException
    Vouchers::shouldReceive('redeem')
        ->once()
        ->with('USED456', $this->contact, ['redemption' => ['at' => '2025-06-14T12:00:00']])
        ->andThrow(new VoucherAlreadyRedeemedException('Already used'));

    // Act
    $result = RedeemVoucher::run($this->contact, 'USED456', ['at' => '2025-06-14T12:00:00']);

    // Assert
    expect($result)->toBeFalse();
});
