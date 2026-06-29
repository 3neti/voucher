<?php

use LBHurtado\Voucher\Services\ExecutionDriverRegistry;

it('all public voucher redemption paths pass through the execution engine adapter', function () {
    $source = file_get_contents(__DIR__.'/../../../src/Actions/RedeemVoucher.php');

    expect($source)->toContain('use LBHurtado\\Voucher\\Services\\ExecutionEngine;')
        ->and($source)->toContain('private readonly ExecutionEngine $executionEngine')
        ->and($source)->toContain('$this->executionEngine->execute(');
});

it('the execution engine resolves drivers only through the registry', function () {
    $source = file_get_contents(__DIR__.'/../../../src/Services/ExecutionEngine.php');

    expect($source)->toContain('private readonly ExecutionDriverRegistry $drivers')
        ->and($source)->toContain('->resolve($this->driverKeyFor($context))')
        ->and($source)->not->toContain('new DefaultExecutionDriver')
        ->and($source)->not->toContain('instanceof DefaultExecutionDriver')
        ->and(preg_match('/\bif\s*\(/', $source))->toBe(0)
        ->and(preg_match('/\bmatch\s*\(/', $source))->toBe(0);
});

it('keeps only the default driver registered by the voucher package initially', function () {
    expect(app(ExecutionDriverRegistry::class)->keys())->toBe(['default']);
});

it('does not scaffold later slice execution drivers during stabilization', function () {
    expect(class_exists('LBHurtado\\Voucher\\Services\\SettlementEnvelopeExecutionDriver'))->toBeFalse()
        ->and(class_exists('LBHurtado\\Voucher\\Services\\StoredValueExecutionDriver'))->toBeFalse();
});
