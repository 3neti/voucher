<?php

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;

beforeEach(function () {
    $this->setupSystemUser();
});

it('builds execution context from a redeemed voucher', function () {
    $voucher = new Voucher([
        'code' => 'PAY-1234',
        'metadata' => [
            'instructions' => [
                'execution' => [
                    'driver' => 'stored-value',
                ],
            ],
        ],
    ]);
    $contact = new Contact(['mobile' => '+639171234567']);

    $context = ExecutionContextData::fromRedemption(
        voucher: $voucher,
        contact: $contact,
        voucherCode: 'PAY-1234',
        meta: ['inputs' => ['name' => 'Rider']],
    );

    expect($context)->toBeInstanceOf(ExecutionContextData::class)
        ->and($context->voucher)->toBe($voucher)
        ->and($context->contact)->toBe($contact)
        ->and($context->voucherCode)->toBe('PAY-1234')
        ->and($context->meta)->toBe(['inputs' => ['name' => 'Rider']])
        ->and($context->instruction)->toBeInstanceOf(ExecutionInstructionData::class)
        ->and($context->instruction->driver)->toBe('stored-value');
});

it('resolves a driver key from execution instructions', function () {
    $engine = app(ExecutionEngine::class);

    $context = new ExecutionContextData(
        contact: new Contact(['mobile' => '+639171234567']),
        voucherCode: 'PAY-1234',
        instruction: ExecutionInstructionData::from(['driver' => 'default']),
    );

    expect($engine->driverKeyFor($context))->toBe('default');
});

it('executes the default driver for the current compatibility path', function () {
    $driver = new class implements ExecutionDriverContract
    {
        public array $calls = [];

        public function key(): string
        {
            return 'default';
        }

        public function execute(ExecutionContextData $context): ExecutionResultData
        {
            $this->calls[] = compact('context');

            return ExecutionResultData::succeeded($this->key());
        }
    };

    $engine = new ExecutionEngine($driver);
    $contact = new Contact(['mobile' => '+639171234567']);

    $result = $engine->execute(new ExecutionContextData(
        contact: $contact,
        voucherCode: 'PAY-1234',
        meta: ['bank_account' => 'BANK:123'],
        instruction: ExecutionInstructionData::from(['driver' => 'default']),
    ));

    expect($result)->toBeInstanceOf(ExecutionResultData::class)
        ->and($result->successful)->toBeTrue()
        ->and($result->status)->toBe('succeeded')
        ->and($result->driver)->toBe('default')
        ->and($driver->calls)->toHaveCount(1)
        ->and($driver->calls[0]['context']->contact)->toBe($contact)
        ->and($driver->calls[0]['context']->voucherCode)->toBe('PAY-1234')
        ->and($driver->calls[0]['context']->meta)->toBe(['bank_account' => 'BANK:123']);
});

it('returns a failed execution result when default driver execution is rejected', function () {
    $driver = new class implements ExecutionDriverContract
    {
        public function key(): string
        {
            return 'default';
        }

        public function execute(ExecutionContextData $context): ExecutionResultData
        {
            return ExecutionResultData::failed(
                driver: $this->key(),
                failure: 'compatibility_redemption_rejected',
            );
        }
    };

    $result = (new ExecutionEngine($driver))->execute(new ExecutionContextData(
        contact: new Contact(['mobile' => '+639171234567']),
        voucherCode: 'PAY-1234',
        instruction: ExecutionInstructionData::from(['driver' => 'default']),
    ));

    expect($result)->toBeInstanceOf(ExecutionResultData::class)
        ->and($result->successful)->toBeFalse()
        ->and($result->status)->toBe('failed')
        ->and($result->failure)->toBe('compatibility_redemption_rejected');
});

it('records execution metadata without changing legacy behavior', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
    ));

    $beforeInstructions = $voucher->metadata['instructions'] ?? [];

    $driver = new class implements ExecutionDriverContract
    {
        public function key(): string
        {
            return 'default';
        }

        public function execute(ExecutionContextData $context): ExecutionResultData
        {
            return ExecutionResultData::succeeded($this->key(), [
                'voucher_code' => $context->voucherCode,
                'driver' => $this->key(),
            ]);
        }
    };

    $result = (new ExecutionEngine($driver))->execute(ExecutionContextData::fromRedemption(
        voucher: $voucher,
        contact: new Contact(['mobile' => '+639171234567']),
        voucherCode: (string) $voucher->code,
    ));

    $voucher->refresh();

    expect($result->metadata)->toMatchArray([
        'voucher_code' => (string) $voucher->code,
        'driver' => 'default',
    ])
        ->and($voucher->metadata['instructions'] ?? [])->toBe($beforeInstructions)
        ->and($voucher->metadata['instructions'] ?? [])->not->toHaveKey('execution');
});
