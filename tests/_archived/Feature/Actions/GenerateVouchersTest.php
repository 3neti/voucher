<?php

use Carbon\CarbonInterval;
use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\CashValidationRulesData;
use LBHurtado\Voucher\Data\FeedbackInstructionData;
use LBHurtado\Voucher\Data\InputFieldsData;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Events\VouchersGenerated;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake();
});

it('generates multiple vouchers using default values', function () {
    // Arrange: Set up default instructions with no custom prefix, mask, or TTL
    $instructions = new VoucherInstructionsData(
        cash: new CashInstructionData(
            amount: 1000,
            currency: 'PHP',
            validation: new CashValidationRulesData(
                secret: '123456',
                mobile: '09171234567',
                country: 'PH',
                location: 'Makati City',
                radius: '1000m'
            )
        ),
        inputs: new InputFieldsData([
            VoucherInputField::EMAIL,
            VoucherInputField::MOBILE,
        ]),
        feedback: new FeedbackInstructionData(
            email: 'feedback@acme.com',
            mobile: '09171234567',
            webhook: 'https://acme.com/webhook',
        ),
        rider: new RiderInstructionData(
            message: 'Welcome!',
            url: 'https://acme.com/rider',
        ),
        count: 1, // Default count
        prefix: null, // Use default prefix from config
        mask: null,   // Use default mask from config
        ttl: null     // Use default TTL (12 hours) from action
    );

    // Act: Run the GenerateVouchers action
    $vouchers = GenerateVouchers::run($instructions);

    // Assert: Check if the vouchers and metadata match expectations
    expect($vouchers)->toHaveCount(1);
    expect($vouchers->first())->toBeInstanceOf(Voucher::class);
    expect($vouchers->first()->instructions->cash->amount)->toBe(1000.0);

    // Assert: Ensure VouchersGenerated event was dispatched
    Event::assertDispatched(VouchersGenerated::class, function ($event) {
        return $event->getVouchers()->count() === 1;
    });
});

it('generates vouchers with custom parameters', function () {
    // Arrange: Instructions with custom prefix, mask, TTL, and count
    $instructions = new VoucherInstructionsData(
        cash: new CashInstructionData(
            amount: 500,
            currency: 'USD',
            validation: new CashValidationRulesData(
                secret: 'abcdef',
                mobile: '09998887777',
                country: 'US',
                location: 'New York',
                radius: '500m'
            )
        ),
        inputs: new InputFieldsData([
            VoucherInputField::KYC,
            VoucherInputField::REFERENCE_CODE,
        ]),
        feedback: new FeedbackInstructionData(
            email: 'support@us.example.com',
            mobile: '09998887777',
            webhook: 'https://us.example.com/webhook',
        ),
        rider: new RiderInstructionData(
            message: 'Claim in USD only',
            url: 'https://us.example.com/rules',
        ),
        count: 2,                 // Custom count
        prefix: 'AA',             // Custom prefix
        mask: '****',             // Custom mask
        ttl: CarbonInterval::hours(12) // Custom TTL (12 hours)
    );

    // Act: Run the GenerateVouchers action
    $vouchers = GenerateVouchers::run($instructions);

    // Assert: Check voucher count and prefix
    expect($vouchers)->toHaveCount(2)
        ->and($vouchers->first()->code)->toStartWith('AA')
        ->and($vouchers->first()->code) // Add assertion to test mask
        ->toMatch('/^'
            .$instructions->prefix // Escape the prefix
            .config('vouchers.separator') // Add the separator after the prefix
            .str_replace('*', '.', $instructions->mask) // Replace '*' with '.' and escape everything else
            .'$/' // Ensure the entire code matches
        )
        ->and($vouchers->first()->instructions->cash->currency)->toBe('USD');

    // Assert: Check metadata

    // Assert: Event dispatching
    Event::assertDispatched(VouchersGenerated::class, function ($event) {
        return $event->getVouchers()->count() === 2;
    });
});
