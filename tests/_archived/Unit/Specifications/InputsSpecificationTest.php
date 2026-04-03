<?php

use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\CashValidationRulesData;
use LBHurtado\Voucher\Data\InputFieldsData;
use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Specifications\InputsSpecification;

describe('InputsSpecification', function () {
    it('returns true when no input fields are required', function () {
        // Voucher with no required inputs
        $instructions = new VoucherInstructionsData(
            cash: new CashInstructionData(
                amount: 100,
                currency: 'PHP',
                validation: new CashValidationRulesData(
                    secret: null,
                    mobile: null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null
                )
            ),
            inputs: new InputFieldsData(fields: [])
        );

        $voucher = new class($instructions)
        {
            public function __construct(public VoucherInstructionsData $instructions) {}
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: []
        );

        $specification = new InputsSpecification;

        expect($specification->passes($voucher, $context))->toBeTrue();
    });

    it('returns true when all required input fields are present', function () {
        // Voucher requires email and name
        $instructions = new VoucherInstructionsData(
            cash: new CashInstructionData(
                amount: 100,
                currency: 'PHP',
                validation: new CashValidationRulesData(
                    secret: null,
                    mobile: null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null
                )
            ),
            inputs: new InputFieldsData(fields: [
                VoucherInputField::EMAIL,
                VoucherInputField::NAME,
            ])
        );

        $voucher = new class($instructions)
        {
            public function __construct(public VoucherInstructionsData $instructions) {}
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => 'test@example.com',
                'name' => 'John Doe',
            ]
        );

        $specification = new InputsSpecification;

        expect($specification->passes($voucher, $context))->toBeTrue();
    });

    it('returns false when required input field is missing', function () {
        // Voucher requires email and name
        $instructions = new VoucherInstructionsData(
            cash: new CashInstructionData(
                amount: 100,
                currency: 'PHP',
                validation: new CashValidationRulesData(
                    secret: null,
                    mobile: null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null
                )
            ),
            inputs: new InputFieldsData(fields: [
                VoucherInputField::EMAIL,
                VoucherInputField::NAME,
            ])
        );

        $voucher = new class($instructions)
        {
            public function __construct(public VoucherInstructionsData $instructions) {}
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => 'test@example.com',
                // Missing 'name'
            ]
        );

        $specification = new InputsSpecification;

        expect($specification->passes($voucher, $context))->toBeFalse();
    });

    it('returns false when required input field is empty string', function () {
        $instructions = new VoucherInstructionsData(
            cash: new CashInstructionData(
                amount: 100,
                currency: 'PHP',
                validation: new CashValidationRulesData(
                    secret: null,
                    mobile: null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null
                )
            ),
            inputs: new InputFieldsData(fields: [
                VoucherInputField::EMAIL,
            ])
        );

        $voucher = new class($instructions)
        {
            public function __construct(public VoucherInstructionsData $instructions) {}
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => '',  // Empty string
            ]
        );

        $specification = new InputsSpecification;

        expect($specification->passes($voucher, $context))->toBeFalse();
    });

    it('returns false when required input field is null', function () {
        $instructions = new VoucherInstructionsData(
            cash: new CashInstructionData(
                amount: 100,
                currency: 'PHP',
                validation: new CashValidationRulesData(
                    secret: null,
                    mobile: null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null
                )
            ),
            inputs: new InputFieldsData(fields: [
                VoucherInputField::EMAIL,
            ])
        );

        $voucher = new class($instructions)
        {
            public function __construct(public VoucherInstructionsData $instructions) {}
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => null,  // Null value
            ]
        );

        $specification = new InputsSpecification;

        expect($specification->passes($voucher, $context))->toBeFalse();
    });

    it('skips special fields like kyc and location', function () {
        // Voucher requires kyc and location (handled by other specs)
        $instructions = new VoucherInstructionsData(
            cash: new CashInstructionData(
                amount: 100,
                currency: 'PHP',
                validation: new CashValidationRulesData(
                    secret: null,
                    mobile: null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null
                )
            ),
            inputs: new InputFieldsData(fields: [
                VoucherInputField::KYC,
                VoucherInputField::LOCATION,
            ])
        );

        $voucher = new class($instructions)
        {
            public function __construct(public VoucherInstructionsData $instructions) {}
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: []  // Empty - but should pass because kyc/location are special
        );

        $specification = new InputsSpecification;

        // Should pass because kyc and location are handled by dedicated specs
        expect($specification->passes($voucher, $context))->toBeTrue();
    });

    it('returns list of missing fields', function () {
        $instructions = new VoucherInstructionsData(
            cash: new CashInstructionData(
                amount: 100,
                currency: 'PHP',
                validation: new CashValidationRulesData(
                    secret: null,
                    mobile: null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null
                )
            ),
            inputs: new InputFieldsData(fields: [
                VoucherInputField::EMAIL,
                VoucherInputField::NAME,
                VoucherInputField::BIRTHDATE,
            ])
        );

        $voucher = new class($instructions)
        {
            public function __construct(public VoucherInstructionsData $instructions) {}
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => 'test@example.com',
                // Missing 'name' and 'birthdate'
            ]
        );

        $specification = new InputsSpecification;
        $missing = $specification->getMissingFields($voucher, $context);

        expect($missing)->toBeArray()
            ->and($missing)->toHaveCount(2)
            ->and($missing)->toContain('name')
            ->and($missing)->toContain('birthdate');
    });

    it('handles mixed regular and special fields correctly', function () {
        $instructions = new VoucherInstructionsData(
            cash: new CashInstructionData(
                amount: 100,
                currency: 'PHP',
                validation: new CashValidationRulesData(
                    secret: null,
                    mobile: null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null
                )
            ),
            inputs: new InputFieldsData(fields: [
                VoucherInputField::EMAIL,
                VoucherInputField::KYC,  // Special
                VoucherInputField::NAME,
            ])
        );

        $voucher = new class($instructions)
        {
            public function __construct(public VoucherInstructionsData $instructions) {}
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            inputs: [
                'email' => 'test@example.com',
                'name' => 'John Doe',
                // KYC not needed here (handled by KycSpecification)
            ]
        );

        $specification = new InputsSpecification;

        // Should pass - email and name provided, kyc skipped
        expect($specification->passes($voucher, $context))->toBeTrue();
    });
});
