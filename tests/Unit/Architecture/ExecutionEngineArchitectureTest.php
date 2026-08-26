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

it('keeps driver-composed pipelines out of the central execution engine', function () {
    $source = file_get_contents(__DIR__.'/../../../src/Services/ExecutionEngine.php');

    expect($source)->not->toContain('ExecutionPipelineRuntime')
        ->and($source)->not->toContain('ExecutionPipelineStepRegistry')
        ->and($source)->not->toContain('pipeline');
});

it('keeps the built-in execution driver list explicit', function () {
    expect(app(ExecutionDriverRegistry::class)->keys())->toBe([
        'default',
        'settlement_envelope',
        'stored_value',
        'payable_collection',
    ]);
});

it('keeps stored-value execution behind the driver gateway seam', function () {
    expect(class_exists('LBHurtado\\Voucher\\Services\\StoredValueExecutionDriver'))->toBeTrue()
        ->and(interface_exists('LBHurtado\\Voucher\\Contracts\\StoredValueExecutionGateway'))->toBeTrue()
        ->and(class_exists('LBHurtado\\Voucher\\Services\\StoredValueVoucher'))->toBeFalse()
        ->and(class_exists('LBHurtado\\Voucher\\Models\\StoredValueVoucher'))->toBeFalse();
});

it('keeps payable collection execution behind the driver gateway seam', function () {
    expect(class_exists('LBHurtado\\Voucher\\Services\\PayableCollectionExecutionDriver'))->toBeTrue()
        ->and(interface_exists('LBHurtado\\Voucher\\Contracts\\PayableCollectionExecutionGateway'))->toBeTrue()
        ->and(class_exists('LBHurtado\\Voucher\\Actions\\CollectVoucherFunds'))->toBeFalse()
        ->and(class_exists('LBHurtado\\Voucher\\Models\\VoucherCollection'))->toBeFalse();
});
