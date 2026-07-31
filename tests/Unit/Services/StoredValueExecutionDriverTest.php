<?php

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Contracts\StoredValueExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Voucher\Services\StoredValueExecutionDriver;

it('activates stored value ownership on redemption', function () {
    $gateway = new FakeStoredValueGateway(balance: 10000);

    $result = (new StoredValueExecutionDriver($gateway))->execute(storedValueContext());

    expect($result)->toBeInstanceOf(ExecutionResultData::class)
        ->and($result->successful)->toBeTrue()
        ->and($result->driver)->toBe('stored_value')
        ->and($result->metadata['stored_value_reference'])->toBe('SV-1')
        ->and($result->metadata['owner_mobile'])->toBe('09171234567')
        ->and($result->metadata['remaining_balance'])->toBe(10000)
        ->and($gateway->calls)->toBe(['activate']);
});

it('accepts canonical nested stored value metadata', function () {
    $gateway = new FakeStoredValueGateway(balance: 10000);

    $result = (new StoredValueExecutionDriver($gateway))->execute(storedValueContext(
        replenishable: true,
        maxBalance: 20000,
        otpRequiredAbove: 5000,
        canonical: true,
    ));

    expect($result->successful)->toBeTrue()
        ->and($result->metadata['stored_value_reference'])->toBe('SV-1')
        ->and($result->metadata['remaining_balance'])->toBe(10000);
});

it('uses canonical nested stored value policy for replenishment', function () {
    $gateway = new FakeStoredValueGateway(balance: 4000);

    $result = (new StoredValueExecutionDriver($gateway))->execute(storedValueContext(
        meta: [
            'operation' => 'replenish',
            'amount' => 2000,
        ],
        replenishable: true,
        maxBalance: 10000,
        canonical: true,
    ));

    expect($result->successful)->toBeTrue()
        ->and($result->events)->toContain('stored_value.replenished')
        ->and($result->metadata['remaining_balance'])->toBe(6000);
});

it('does not disburse cash on ownership claim', function () {
    $gateway = new FakeStoredValueGateway(balance: 10000);

    $result = (new StoredValueExecutionDriver($gateway))->execute(storedValueContext());

    expect($result->events)->toContain('stored_value.ownership_claimed')
        ->and($result->events)->not->toContain('cash.disbursed')
        ->and($result->providerReferences)->toBe([])
        ->and($result->metadata['disbursement_skipped'])->toBeTrue();
});

it('allows slice spending after activation', function () {
    $gateway = new FakeStoredValueGateway(balance: 10000);

    $result = (new StoredValueExecutionDriver($gateway))->execute(storedValueContext(meta: [
        'operation' => 'spend',
        'amount' => 2500,
        'merchant_reference' => 'MERCHANT-1',
    ]));

    expect($result->successful)->toBeTrue()
        ->and($result->events)->toContain('stored_value.spent')
        ->and($result->metadata['spent_amount'])->toBe(2500)
        ->and($result->metadata['remaining_balance'])->toBe(7500)
        ->and($gateway->spends)->toBe([
            [
                'amount' => 2500,
                'merchant_reference' => 'MERCHANT-1',
                'execution_id' => $result->execution_id,
            ],
        ]);
});

it('rejects spending above remaining balance', function () {
    $gateway = new FakeStoredValueGateway(balance: 1000);

    $result = (new StoredValueExecutionDriver($gateway))->execute(storedValueContext(meta: [
        'operation' => 'spend',
        'amount' => 2500,
    ]));

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('stored_value_spend_rejected')
        ->and($result->metadata['remaining_balance'])->toBe(1000)
        ->and($result->events)->toBe(['stored_value.spend_requested']);
});

it('requires OTP above configured spend threshold', function () {
    $gateway = new FakeStoredValueGateway(balance: 10000);

    $result = (new StoredValueExecutionDriver($gateway))->execute(storedValueContext(meta: [
        'operation' => 'spend',
        'amount' => 1500,
    ], otpRequiredAbove: 1000));

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('stored_value_otp_required')
        ->and($result->metadata['otp_required_above'])->toBe(1000)
        ->and($result->metadata['requested_amount'])->toBe(1500);
});

it('supports replenishable vouchers when configured', function () {
    $gateway = new FakeStoredValueGateway(balance: 4000);

    $result = (new StoredValueExecutionDriver($gateway))->execute(storedValueContext(meta: [
        'operation' => 'replenish',
        'amount' => 2000,
    ], replenishable: true, maxBalance: 10000));

    expect($result->successful)->toBeTrue()
        ->and($result->events)->toContain('stored_value.replenished')
        ->and($result->metadata['replenished_amount'])->toBe(2000)
        ->and($result->metadata['remaining_balance'])->toBe(6000);
});

it('rejects replenishment above the configured max balance', function () {
    $gateway = new FakeStoredValueGateway(balance: 9000);

    $result = (new StoredValueExecutionDriver($gateway))->execute(storedValueContext(meta: [
        'operation' => 'replenish',
        'amount' => 2000,
    ], replenishable: true, maxBalance: 10000));

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('stored_value_replenishment_rejected')
        ->and($result->metadata['remaining_balance'])->toBe(9000)
        ->and($gateway->calls)->toBe(['balance']);
});

it('rejects replenishment when the voucher is not replenishable', function () {
    $gateway = new FakeStoredValueGateway(balance: 4000);

    $result = (new StoredValueExecutionDriver($gateway))->execute(storedValueContext(meta: [
        'operation' => 'replenish',
        'amount' => 2000,
    ], replenishable: false));

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('stored_value_replenishment_rejected')
        ->and($gateway->calls)->toBe([]);
});

it('registers the stored value driver without changing default or settlement-envelope resolution', function () {
    $registry = app(ExecutionDriverRegistry::class);

    expect($registry->resolve('stored_value'))->toBeInstanceOf(StoredValueExecutionDriver::class)
        ->and($registry->resolve('default')->key())->toBe('default')
        ->and($registry->resolve('settlement_envelope')->key())->toBe('settlement_envelope');
});

it('executes stored value instructions through the registry', function () {
    app()->instance(StoredValueExecutionGateway::class, new FakeStoredValueGateway(balance: 10000));

    $result = app(ExecutionEngine::class)->execute(storedValueContext());

    expect($result->successful)->toBeTrue()
        ->and($result->driver)->toBe('stored_value');
});

function storedValueContext(
    array $meta = [],
    bool $replenishable = false,
    int $maxBalance = 10000,
    int $otpRequiredAbove = 0,
    bool $canonical = false,
): ExecutionContextData {
    $metadata = $canonical
        ? [
            'stored_value' => [
                'reference' => 'SV-1',
                'max_balance' => $maxBalance,
                'replenishable' => $replenishable,
                'otp_required_above' => $otpRequiredAbove,
            ],
        ]
        : [
            'stored_value_reference' => 'SV-1',
            'replenishable' => $replenishable,
            'max_balance' => $maxBalance,
            'otp_required_above' => $otpRequiredAbove,
        ];

    return new ExecutionContextData(
        contact: new Contact(['mobile' => '+639171234567']),
        voucherCode: 'STORED-1',
        meta: $meta,
        instruction: ExecutionInstructionData::from([
            'driver' => 'stored_value',
            'metadata' => $metadata,
        ]),
    );
}

class FakeStoredValueGateway implements StoredValueExecutionGateway
{
    public array $calls = [];

    public array $spends = [];

    public function __construct(
        private int $balance,
    ) {}

    public function activate(ExecutionContextData $context, string $executionId): array
    {
        $this->calls[] = 'activate';

        return $this->state($context);
    }

    public function spend(ExecutionContextData $context, int $amount, string $executionId): array
    {
        $this->calls[] = 'spend';

        if ($amount > $this->balance) {
            throw new StoredValueSpendRejectedException('Insufficient stored value balance.');
        }

        $this->balance -= $amount;
        $this->spends[] = [
            'amount' => $amount,
            'merchant_reference' => $context->meta['merchant_reference'] ?? null,
            'execution_id' => $executionId,
        ];

        return $this->state($context);
    }

    public function replenish(ExecutionContextData $context, int $amount, string $executionId): array
    {
        $this->calls[] = 'replenish';

        $this->balance += $amount;

        return $this->state($context);
    }

    public function balance(ExecutionContextData $context): int
    {
        $this->calls[] = 'balance';

        return $this->balance;
    }

    private function state(ExecutionContextData $context): array
    {
        return [
            'stored_value_reference' => data_get($context->instruction?->metadata, 'stored_value.reference')
                ?? $context->instruction?->metadata['stored_value_reference'],
            'owner_mobile' => $context->contact->mobile,
            'remaining_balance' => $this->balance,
        ];
    }
}
