<?php

use Carbon\CarbonInterval;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\CashValidationRulesData;
use LBHurtado\Voucher\Data\FeedbackInstructionData;
use LBHurtado\Voucher\Data\InputFieldsData;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;

uses(RefreshDatabase::class);

it('creates vouchers using updated data structures and verifies new parameters', function () {
    // Arrange: Create VoucherInstructionsData object
    $instructions = new VoucherInstructionsData(
        cash: new CashInstructionData(
            amount: 1500,
            currency: 'USD',
            validation: new CashValidationRulesData(
                secret: 'abcdef',
                mobile: '09179876543',
                country: 'US',
                location: 'New York',
                radius: '5000m'
            )
        ),
        inputs: new InputFieldsData([
            VoucherInputField::EMAIL,
            VoucherInputField::MOBILE,
            VoucherInputField::REFERENCE_CODE,
        ]),
        feedback: new FeedbackInstructionData(
            email: 'support@company.com',
            mobile: '09179876543',
            webhook: 'https://company.com/webhook',
        ),
        rider: new RiderInstructionData(
            message: 'Welcome to our company!',
            url: 'https://company.com/rider-url',
        ),
        count: 2,                                  // Number of vouchers to generate
        prefix: 'TEST',                            // Prefix for voucher codes
        mask: '****-****',                         // Mask for voucher codes
        ttl: CarbonInterval::hours(24),            // Expiry time (TTL)
    );

    // Act: Create multiple vouchers in one call
    $vouchers = Vouchers::withPrefix($instructions->prefix)
        ->withMask($instructions->mask)
        ->withMetadata([
            'instructions' => $instructions->toArray(),
        ])
        ->withExpireTimeIn($instructions->ttl)
        ->create($instructions->count); // Count controls how many vouchers to create

    // Assert: Verify the vouchers were created successfully
    expect($vouchers)->toHaveCount($instructions->count);

    foreach ($vouchers as $voucher) {
        expect($voucher)->not->toBeNull()
            ->and($voucher)->toBeInstanceOf(Voucher::class)
            ->and($voucher->code)->toStartWith('TEST')
            ->and($voucher->code) // Add assertion to test mask
            ->toMatch('/^'
                .$instructions->prefix // Escape the prefix
                .config('vouchers.separator') // Add the separator after the prefix
                .str_replace('*', '.', $instructions->mask) // Replace '*' with '.' and escape everything else
                .'$/' // Ensure the entire code matches
            )
            ->and($voucher->instructions->cash->amount)->toBe(1500.0)
            ->and($voucher->instructions->cash->currency)->toBe('USD')
            ->and($voucher->instructions->inputs->fields)->toContain(VoucherInputField::EMAIL, VoucherInputField::MOBILE, VoucherInputField::REFERENCE_CODE);
        //            ->and($voucher->expires_at->diffInHours(now()))->toBe(24) // Validate expiration time
    }
});
