<?php

/**
 * HasEnvelopes Trait Unit Tests
 *
 * These tests verify that the Voucher model correctly uses the HasEnvelopes trait
 * from the settlement-envelope package.
 *
 * Note: Due to the voucher package's complex dependencies (media-library, etc.),
 * we use file-based verification instead of runtime class loading.
 *
 * For full integration testing of the envelope lifecycle, use:
 * - Host app: `php artisan test:envelope`
 * - Shell script: `scripts/testing/test-envelope-flow.sh`
 */
describe('HasEnvelopes trait on Voucher model', function () {
    test('voucher model file contains HasEnvelopes trait import', function () {
        $voucherFile = file_get_contents(__DIR__.'/../../../src/Models/Voucher.php');

        expect($voucherFile)->toContain('use LBHurtado\SettlementEnvelope\Traits\HasEnvelopes;');
    });

    test('voucher model file uses HasEnvelopes trait', function () {
        $voucherFile = file_get_contents(__DIR__.'/../../../src/Models/Voucher.php');

        // Check for "use HasEnvelopes;" within the class body
        expect($voucherFile)->toMatch('/class Voucher[^{]*\{[^}]*use HasEnvelopes;/s');
    });

    test('HasEnvelopes trait exists in settlement-envelope package', function () {
        expect(trait_exists('LBHurtado\SettlementEnvelope\Traits\HasEnvelopes'))->toBeTrue();
    });

    test('HasEnvelopes trait has envelopes method', function () {
        $reflection = new ReflectionClass('LBHurtado\SettlementEnvelope\Traits\HasEnvelopes');

        expect($reflection->hasMethod('envelopes'))->toBeTrue();
    });

    test('HasEnvelopes trait has createEnvelope method', function () {
        $reflection = new ReflectionClass('LBHurtado\SettlementEnvelope\Traits\HasEnvelopes');

        expect($reflection->hasMethod('createEnvelope'))->toBeTrue();
    });

    test('HasEnvelopes trait has envelope (singular) method', function () {
        $reflection = new ReflectionClass('LBHurtado\SettlementEnvelope\Traits\HasEnvelopes');

        expect($reflection->hasMethod('envelope'))->toBeTrue();
    });

    test('HasEnvelopes trait has hasEnvelope method', function () {
        $reflection = new ReflectionClass('LBHurtado\SettlementEnvelope\Traits\HasEnvelopes');

        expect($reflection->hasMethod('hasEnvelope'))->toBeTrue();
    });

    test('HasEnvelopes trait has isEnvelopeSettleable method', function () {
        $reflection = new ReflectionClass('LBHurtado\SettlementEnvelope\Traits\HasEnvelopes');

        expect($reflection->hasMethod('isEnvelopeSettleable'))->toBeTrue();
    });

    test('HasEnvelopes trait has getEnvelopeReferenceCode method', function () {
        $reflection = new ReflectionClass('LBHurtado\SettlementEnvelope\Traits\HasEnvelopes');

        expect($reflection->hasMethod('getEnvelopeReferenceCode'))->toBeTrue();
    });
});
