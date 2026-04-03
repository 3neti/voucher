<?php

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Specifications\SecretSpecification;

describe('SecretSpecification', function () {
    it('returns true when secret matches', function () {
        // Create a mock voucher with instructions
        $instructions = new VoucherInstructionsData(
            cash: new \LBHurtado\Voucher\Data\CashInstructionData(
                amount: 100,
                validation: new \LBHurtado\Voucher\Data\CashValidationRulesData(
                    secret: 'ABC123',
                    mobile: null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null
                )
            )
        );

        $voucher = new class($instructions)
        {
            public function __construct(public VoucherInstructionsData $instructions) {}
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            secret: 'ABC123'
        );

        $specification = new SecretSpecification;

        expect($specification->passes($voucher, $context))->toBeTrue();
    });

    it('returns false when secret does not match', function () {
        $instructions = new VoucherInstructionsData(
            cash: new \LBHurtado\Voucher\Data\CashInstructionData(
                amount: 100,
                validation: new \LBHurtado\Voucher\Data\CashValidationRulesData(
                    secret: 'ABC123',
                    mobile: null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null
                )
            )
        );

        $voucher = new class($instructions)
        {
            public function __construct(public VoucherInstructionsData $instructions) {}
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            secret: 'WRONG'
        );

        $specification = new SecretSpecification;

        expect($specification->passes($voucher, $context))->toBeFalse();
    });

    it('returns false when context secret is null but voucher requires it', function () {
        $instructions = new VoucherInstructionsData(
            cash: new \LBHurtado\Voucher\Data\CashInstructionData(
                amount: 100,
                validation: new \LBHurtado\Voucher\Data\CashValidationRulesData(
                    secret: 'ABC123',
                    mobile: null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null
                )
            )
        );

        $voucher = new class($instructions)
        {
            public function __construct(public VoucherInstructionsData $instructions) {}
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            secret: null
        );

        $specification = new SecretSpecification;

        expect($specification->passes($voucher, $context))->toBeFalse();
    });

    it('returns true when voucher does not require secret', function () {
        $instructions = new VoucherInstructionsData(
            cash: new \LBHurtado\Voucher\Data\CashInstructionData(
                amount: 100,
                validation: new \LBHurtado\Voucher\Data\CashValidationRulesData(
                    secret: null,
                    mobile: null,
                    payable: null,
                    country: null,
                    location: null,
                    radius: null
                )
            )
        );

        $voucher = new class($instructions)
        {
            public function __construct(public VoucherInstructionsData $instructions) {}
        };

        $context = new RedemptionContext(
            mobile: '+639171234567',
            secret: null
        );

        $specification = new SecretSpecification;

        expect($specification->passes($voucher, $context))->toBeTrue();
    });
});
