<?php

use LBHurtado\Voucher\Contracts\PayableCollectionExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Exceptions\PayableCollectionRejectedException;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Voucher\Services\NullPayableCollectionExecutionGateway;
use LBHurtado\Voucher\Services\PayableCollectionExecutionDriver;

it('authorizes and credits a payable collection without fabricating a contact', function () {
    $gateway = new FakePayableCollectionExecutionGateway;
    $context = payableCollectionContext();

    $result = (new PayableCollectionExecutionDriver($gateway))->execute($context);

    expect($context->contact)->toBeNull()
        ->and($result)->toBeInstanceOf(ExecutionResultData::class)
        ->and($result->successful)->toBeTrue()
        ->and($result->execution_id)->toBe('payment-attempt:PA-0001')
        ->and($result->driver)->toBe('payable_collection')
        ->and($result->events)->toBe([
            'payable_collection.collection_requested',
            'payable_collection.authorized',
            'payable_collection.credited',
        ])
        ->and($result->metadata)->toMatchArray([
            'authority_voucher_code' => 'PAYABLE-1',
            'wallet_authorized' => true,
            'voucher_collection_id' => 73,
            'collected_amount_minor' => 5000,
        ])
        ->and($gateway->calls)->toBe([
            ['authorize', 'payment-attempt:PA-0001'],
            ['credit', 5000, 'provider-transaction-1', 'payment-attempt:PA-0001'],
        ]);
});

it('returns a structured failure when collection authority is rejected', function () {
    $gateway = new FakePayableCollectionExecutionGateway(
        rejectAuthorization: true,
    );

    $result = (new PayableCollectionExecutionDriver($gateway))->execute(
        payableCollectionContext(),
    );

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('payable_collection_rejected')
        ->and($result->execution_id)->toBe('payment-attempt:PA-0001')
        ->and($result->events)->toBe(['payable_collection.collection_requested'])
        ->and($result->metadata['message'])->toBe('Collection wallet authority was rejected.')
        ->and($gateway->calls)->toBe([
            ['authorize', 'payment-attempt:PA-0001'],
        ]);
});

it('returns a structured failure when an authorized credit is rejected', function () {
    $gateway = new FakePayableCollectionExecutionGateway(
        rejectCredit: true,
    );

    $result = (new PayableCollectionExecutionDriver($gateway))->execute(
        payableCollectionContext(),
    );

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('payable_collection_rejected')
        ->and($result->events)->toBe([
            'payable_collection.collection_requested',
            'payable_collection.authorized',
        ])
        ->and($result->metadata['message'])->toBe('Collection credit was rejected.');
});

it('fails closed for missing or unsupported collection operations', function (?string $operation) {
    $gateway = new FakePayableCollectionExecutionGateway;
    $context = payableCollectionContext();
    $context->meta = array_filter([
        'operation' => $operation,
        'amount_minor' => 5000,
        'provider_transaction_id' => 'provider-transaction-1',
    ], static fn (mixed $value): bool => $value !== null);

    $result = (new PayableCollectionExecutionDriver($gateway))->execute($context);

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('payable_collection_operation_unsupported')
        ->and($gateway->calls)->toBe([]);
})->with([
    'missing' => null,
    'unsupported' => 'refund',
]);

it('registers a fail closed payable collection driver without changing existing drivers', function () {
    $registry = app(ExecutionDriverRegistry::class);

    expect(app(PayableCollectionExecutionGateway::class))
        ->toBeInstanceOf(NullPayableCollectionExecutionGateway::class)
        ->and($registry->resolve('payable_collection'))
        ->toBeInstanceOf(PayableCollectionExecutionDriver::class)
        ->and($registry->resolve('default')->key())->toBe('default')
        ->and($registry->resolve('settlement_envelope')->key())->toBe('settlement_envelope')
        ->and($registry->resolve('stored_value')->key())->toBe('stored_value');

    $result = app(ExecutionEngine::class)->execute(payableCollectionContext());

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('payable_collection_rejected')
        ->and($result->metadata['message'])
        ->toBe('No payable collection execution gateway is configured.');
});

function payableCollectionContext(): ExecutionContextData
{
    return new ExecutionContextData(
        contact: null,
        voucherCode: 'PAYABLE-1',
        meta: [
            'operation' => 'collect',
            'amount_minor' => 5000,
            'provider_transaction_id' => 'provider-transaction-1',
        ],
        instruction: ExecutionInstructionData::from([
            'driver' => 'payable_collection',
        ]),
        correlation: [
            'execution_id' => 'payment-attempt:PA-0001',
        ],
    );
}

class FakePayableCollectionExecutionGateway implements PayableCollectionExecutionGateway
{
    /** @var array<int, array<int, int|string>> */
    public array $calls = [];

    public function __construct(
        private readonly bool $rejectAuthorization = false,
        private readonly bool $rejectCredit = false,
    ) {}

    public function authorize(ExecutionContextData $context, string $executionId): array
    {
        $this->calls[] = ['authorize', $executionId];

        if ($this->rejectAuthorization) {
            throw new PayableCollectionRejectedException(
                'Collection wallet authority was rejected.',
            );
        }

        return ['wallet_authorized' => true];
    }

    public function credit(
        ExecutionContextData $context,
        int $amountMinor,
        string $providerTransactionId,
        string $executionId,
    ): array {
        $this->calls[] = [
            'credit',
            $amountMinor,
            $providerTransactionId,
            $executionId,
        ];

        if ($this->rejectCredit) {
            throw new PayableCollectionRejectedException('Collection credit was rejected.');
        }

        return ['voucher_collection_id' => 73];
    }
}
