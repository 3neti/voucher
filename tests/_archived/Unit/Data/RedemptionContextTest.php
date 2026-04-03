<?php

use LBHurtado\Voucher\Data\RedemptionContext;

describe('RedemptionContext', function () {
    it('can be instantiated with required mobile field', function () {
        $context = new RedemptionContext(
            mobile: '+639171234567'
        );

        expect($context->mobile)->toBe('+639171234567')
            ->and($context->secret)->toBeNull()
            ->and($context->vendorAlias)->toBeNull()
            ->and($context->inputs)->toBe([])
            ->and($context->bankAccount)->toBe([]);
    });

    it('can be instantiated with all fields', function () {
        $context = new RedemptionContext(
            mobile: '+639171234567',
            secret: 'ABC123',
            vendorAlias: 'BB',
            inputs: ['location' => ['lat' => 14.5995, 'lng' => 120.9842]],
            bankAccount: ['account_number' => '123456789']
        );

        expect($context->mobile)->toBe('+639171234567')
            ->and($context->secret)->toBe('ABC123')
            ->and($context->vendorAlias)->toBe('BB')
            ->and($context->inputs)->toHaveKey('location')
            ->and($context->bankAccount)->toHaveKey('account_number');
    });

    it('accepts optional fields as null', function () {
        $context = new RedemptionContext(
            mobile: '+639171234567',
            secret: null,
            vendorAlias: null
        );

        expect($context->secret)->toBeNull()
            ->and($context->vendorAlias)->toBeNull();
    });
});
