<?php

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Services\DefaultExecutionDriver;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;
use LBHurtado\Voucher\Services\ExecutionEngine;

beforeEach(function () {
    $this->setupSystemUser();
});

it('uses the default driver when no execution instruction exists', function () {
    $context = new ExecutionContextData(
        contact: new Contact(['mobile' => '+639171234567']),
        voucherCode: 'PAY-1234',
    );

    expect(app(ExecutionDriverContract::class))->toBeInstanceOf(DefaultExecutionDriver::class)
        ->and(app(ExecutionEngine::class)->driverKeyFor($context))->toBe('default');
});

it('uses the default driver when execution driver is default', function () {
    $context = new ExecutionContextData(
        contact: new Contact(['mobile' => '+639171234567']),
        voucherCode: 'PAY-1234',
        instruction: ExecutionInstructionData::from(['driver' => 'default']),
    );

    expect(app(ExecutionEngine::class)->driverKeyFor($context))->toBe('default');
});

it('delegates execution to the default driver', function () {
    $driver = new class implements ExecutionDriverContract
    {
        public array $contexts = [];

        public function key(): string
        {
            return 'default';
        }

        public function execute(ExecutionContextData $context): ExecutionResultData
        {
            $this->contexts[] = $context;

            return ExecutionResultData::succeeded($this->key(), [
                'voucher_code' => $context->voucherCode,
            ]);
        }
    };

    $registry = new ExecutionDriverRegistry(app());
    $registry->register('default', $driver);

    $engine = new ExecutionEngine($registry);
    $contact = new Contact(['mobile' => '+639171234567']);

    $result = $engine->execute(new ExecutionContextData(
        contact: $contact,
        voucherCode: 'PAY-1234',
        meta: ['bank_account' => 'BANK:123'],
    ));

    expect($result->successful)->toBeTrue()
        ->and($result->driver)->toBe('default')
        ->and($driver->contexts)->toHaveCount(1)
        ->and($driver->contexts[0]->contact)->toBe($contact)
        ->and($driver->contexts[0]->voucherCode)->toBe('PAY-1234')
        ->and($driver->contexts[0]->meta)->toBe(['bank_account' => 'BANK:123']);
});

it('routes redeem voucher action through the execution engine and default driver', function () {
    $driver = new class implements ExecutionDriverContract
    {
        public array $contexts = [];

        public function key(): string
        {
            return 'default';
        }

        public function execute(ExecutionContextData $context): ExecutionResultData
        {
            $this->contexts[] = $context;

            return ExecutionResultData::succeeded($this->key());
        }
    };

    app(ExecutionDriverRegistry::class)->register('default', $driver);

    $contact = new Contact(['mobile' => '+639171234567']);

    expect(RedeemVoucher::run($contact, 'PAY-1234', ['bank_account' => 'BANK:123']))->toBeTrue()
        ->and($driver->contexts)->toHaveCount(1)
        ->and($driver->contexts[0]->contact)->toBe($contact)
        ->and($driver->contexts[0]->voucherCode)->toBe('PAY-1234')
        ->and($driver->contexts[0]->meta)->toBe(['bank_account' => 'BANK:123']);
});
