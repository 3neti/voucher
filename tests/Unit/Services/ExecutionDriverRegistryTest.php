<?php

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Exceptions\UnknownExecutionDriverException;
use LBHurtado\Voucher\Services\DefaultExecutionDriver;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Voucher\Services\SettlementEnvelopeExecutionDriver;

beforeEach(function () {
    $this->setupSystemUser();
});

it('registers execution drivers by key', function () {
    $registry = new ExecutionDriverRegistry(app());
    $driver = new class implements ExecutionDriverContract
    {
        public function key(): string
        {
            return 'custom';
        }

        public function execute(ExecutionContextData $context): ExecutionResultData
        {
            return ExecutionResultData::succeeded($this->key());
        }
    };

    $registry->register('custom', $driver);

    expect($registry->has('custom'))->toBeTrue()
        ->and($registry->resolve('custom'))->toBe($driver)
        ->and($registry->keys())->toBe(['custom']);
});

it('resolves the default driver by key from the package singleton registry', function () {
    $registry = app(ExecutionDriverRegistry::class);

    expect($registry)->toBe(app(ExecutionDriverRegistry::class))
        ->and($registry->has('default'))->toBeTrue()
        ->and($registry->keys())->toBe(['default', 'settlement_envelope'])
        ->and($registry->resolve('default'))->toBeInstanceOf(DefaultExecutionDriver::class);
});

it('resolves the settlement envelope driver by key from the package singleton registry', function () {
    $registry = app(ExecutionDriverRegistry::class);

    expect($registry->has('settlement_envelope'))->toBeTrue()
        ->and($registry->resolve('settlement_envelope'))->toBeInstanceOf(SettlementEnvelopeExecutionDriver::class);
});

it('throws a clear exception for unknown execution drivers', function () {
    $registry = app(ExecutionDriverRegistry::class);

    expect(fn () => $registry->resolve('imaginary_driver'))
        ->toThrow(UnknownExecutionDriverException::class, 'Unknown execution driver [imaginary_driver].');
});

it('fails closed before executing side effects for unknown execution drivers', function () {
    $driver = new class implements ExecutionDriverContract
    {
        public bool $executed = false;

        public function key(): string
        {
            return 'default';
        }

        public function execute(ExecutionContextData $context): ExecutionResultData
        {
            $this->executed = true;

            return ExecutionResultData::succeeded($this->key());
        }
    };

    $registry = new ExecutionDriverRegistry(app());
    $registry->register('default', $driver);

    $engine = new ExecutionEngine($registry);

    expect(fn () => $engine->execute(new ExecutionContextData(
        contact: new Contact(['mobile' => '+639171234567']),
        voucherCode: 'PAY-1234',
        instruction: ExecutionInstructionData::from(['driver' => 'imaginary_driver']),
    )))->toThrow(UnknownExecutionDriverException::class)
        ->and($driver->executed)->toBeFalse();
});

it('does not use if-else chains for driver selection', function () {
    $source = file_get_contents(__DIR__.'/../../../src/Services/ExecutionEngine.php');

    expect($source)->not->toContain('if (')
        ->and($source)->not->toContain('else')
        ->and($source)->not->toContain('match (');
});

it('allows package consumers to extend driver registrations', function () {
    $registry = app(ExecutionDriverRegistry::class);
    $driver = new class implements ExecutionDriverContract
    {
        public function key(): string
        {
            return 'package_extension';
        }

        public function execute(ExecutionContextData $context): ExecutionResultData
        {
            return ExecutionResultData::succeeded($this->key(), [
                'voucher_code' => $context->voucherCode,
            ]);
        }
    };

    $registry->register('package_extension', $driver);

    $result = app(ExecutionEngine::class)->execute(new ExecutionContextData(
        contact: new Contact(['mobile' => '+639171234567']),
        voucherCode: 'PAY-1234',
        instruction: ExecutionInstructionData::from(['driver' => 'package_extension']),
    ));

    expect($result->successful)->toBeTrue()
        ->and($result->driver)->toBe('package_extension')
        ->and($result->metadata['voucher_code'])->toBe('PAY-1234');
});
