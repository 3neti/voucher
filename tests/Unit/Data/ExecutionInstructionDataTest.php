<?php

use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Exceptions\UnsupportedExecutionInstructionSchemaException;

it('creates a default execution instruction when no execution block is provided', function () {
    $instructions = VoucherInstructionsData::createFromAttribs([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'country' => 'PH',
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
    ]);

    expect($instructions->execution)->toBeNull()
        ->and($instructions->executionInstruction())->toBeInstanceOf(ExecutionInstructionData::class)
        ->and($instructions->executionInstruction()->schema)->toBe('voucher.execution.v1')
        ->and($instructions->executionInstruction()->driver)->toBe('default')
        ->and($instructions->toCleanArray())->not->toHaveKey('execution');
});

it('hydrates execution instructions from voucher instructions', function () {
    $instructions = VoucherInstructionsData::createFromAttribs([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'country' => 'PH',
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
        'execution' => [
            'driver' => 'settlement-envelope',
            'mode' => 'authorization-gated',
            'pipeline' => ['validate', 'authorize'],
            'fallback' => 'default',
            'visibility' => ['journal', 'operator'],
            'metadata' => [
                'envelope_type' => 'philhealth_bst',
            ],
        ],
    ]);

    expect($instructions->execution)->toBeInstanceOf(ExecutionInstructionData::class)
        ->and($instructions->executionInstruction()->schema)->toBe('voucher.execution.v1')
        ->and($instructions->executionInstruction()->driver)->toBe('settlement-envelope')
        ->and($instructions->executionInstruction()->mode)->toBe('authorization-gated')
        ->and($instructions->executionInstruction()->pipeline)->toBe(['validate', 'authorize'])
        ->and($instructions->executionInstruction()->fallback)->toBe('default')
        ->and($instructions->executionInstruction()->visibility)->toBe(['journal', 'operator'])
        ->and($instructions->executionInstruction()->metadata)->toBe([
            'envelope_type' => 'philhealth_bst',
        ]);
});

it('defaults the execution driver to default', function () {
    $instruction = ExecutionInstructionData::from([]);

    expect($instruction->schema)->toBe('voucher.execution.v1')
        ->and($instruction->driver)->toBe('default')
        ->and($instruction->mode)->toBeNull()
        ->and($instruction->pipeline)->toBeNull()
        ->and($instruction->fallback)->toBeNull()
        ->and($instruction->visibility)->toBeNull()
        ->and($instruction->metadata)->toBeNull();
});

it('preserves legacy voucher instruction hydration', function () {
    $instructions = VoucherInstructionsData::createFromAttribs([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'country' => 'PH',
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
        'metadata' => [
            'flow_type' => 'disbursable',
        ],
    ]);

    expect($instructions->cash->amount)->toBe(100.0)
        ->and($instructions->cash->currency)->toBe('PHP')
        ->and($instructions->metadata?->flow_type)->toBe('disbursable')
        ->and($instructions->execution)->toBeNull()
        ->and($instructions->executionInstruction()->schema)->toBe('voucher.execution.v1')
        ->and($instructions->executionInstruction()->driver)->toBe('default');
});

it('preserves explicit execution schema versions', function () {
    $instruction = ExecutionInstructionData::from([
        'schema' => 'voucher.execution.v1',
        'driver' => 'default',
    ]);

    expect($instruction->schema)->toBe('voucher.execution.v1')
        ->and($instruction->driver)->toBe('default');
});

it('rejects unsupported explicit execution schema versions', function () {
    ExecutionInstructionData::from([
        'schema' => 'voucher.execution.v2',
        'driver' => 'default',
    ]);
})->throws(UnsupportedExecutionInstructionSchemaException::class, 'Unsupported execution instruction schema [voucher.execution.v2].');

it('serializes execution instructions into voucher instruction payloads', function () {
    $instructions = VoucherInstructionsData::createFromAttribs([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => [
                'country' => 'PH',
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
        'execution' => [
            'driver' => 'stored-value',
            'metadata' => [
                'ledger' => 'beneficiary_wallet',
            ],
        ],
    ]);

    expect(data_get($instructions->toCleanArray(), 'execution.schema'))->toBe('voucher.execution.v1')
        ->and(data_get($instructions->toCleanArray(), 'execution.driver'))->toBe('stored-value')
        ->and(data_get($instructions->toCleanArray(), 'execution.metadata.ledger'))->toBe('beneficiary_wallet');
});
