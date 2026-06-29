<?php

use Illuminate\Support\Str;
use LBHurtado\Voucher\Data\ExecutionResultData;

it('returns canonical execution result payloads with a durable execution id', function () {
    $result = ExecutionResultData::succeeded('default', [
        'voucher_code' => 'PAY-1234',
    ]);

    expect($result->execution_id)->toBeString()
        ->and(Str::isUuid($result->execution_id))->toBeTrue()
        ->and($result->successful)->toBeTrue()
        ->and($result->status)->toBe('succeeded')
        ->and($result->driver)->toBe('default')
        ->and($result->events)->toBe([])
        ->and($result->metadata)->toBe([
            'voucher_code' => 'PAY-1234',
        ])
        ->and($result->toArray())->toHaveKeys([
            'execution_id',
            'successful',
            'status',
            'driver',
            'events',
            'failure',
            'providerReferences',
            'reconciliation',
            'children',
            'metadata',
        ]);
});

it('preserves a supplied execution id for correlation handoff', function () {
    $result = new ExecutionResultData(
        execution_id: 'execution-fixed-123',
        successful: false,
        status: 'failed',
        driver: 'default',
        failure: 'compatibility_redemption_rejected',
    );

    expect($result->execution_id)->toBe('execution-fixed-123')
        ->and($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('compatibility_redemption_rejected');
});

it('assigns execution ids to failed results too', function () {
    $result = ExecutionResultData::failed(
        driver: 'default',
        failure: 'compatibility_redemption_rejected',
    );

    expect(Str::isUuid($result->execution_id))->toBeTrue()
        ->and($result->successful)->toBeFalse()
        ->and($result->status)->toBe('failed');
});
