<?php

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

function documentInstruction(array $execution): array
{
    return [
        'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => ['country' => 'PH']],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
        'execution' => $execution,
    ];
}

it('round trips v2 document requirements through the shared voucher contract', function (): void {
    $requirements = [[
        'type' => 'IDENTITY',
        'timing' => 'before_payment',
        'requires_acceptance' => true,
        'contributor' => 'payer',
    ]];
    $instruction = VoucherInstructionsData::createFromAttribs(documentInstruction([
        'schema' => 'voucher.execution.v2', 'driver' => 'default', 'document_requirements' => $requirements,
    ]));

    expect($instruction->execution->document_requirements)->toBe($requirements)
        ->and(data_get($instruction->toCleanArray(), 'execution.document_requirements'))->toBe($requirements);
});

it('rejects document requirements in v1', function (): void {
    VoucherInstructionsData::createFromAttribs(documentInstruction([
        'schema' => 'voucher.execution.v1',
        'document_requirements' => [['type' => 'IDENTITY', 'timing' => 'optional', 'requires_acceptance' => false, 'contributor' => 'payer']],
    ]));
})->throws(ValidationException::class);

it('rejects unspecified contributors', function (): void {
    VoucherInstructionsData::createFromAttribs(documentInstruction([
        'schema' => 'voucher.execution.v2',
        'document_requirements' => [['type' => 'IDENTITY', 'timing' => 'before_payment', 'requires_acceptance' => true]],
    ]));
})->throws(ValidationException::class);
