<?php

use Illuminate\Support\Collection;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Contracts\GeneratesVouchers;
use LBHurtado\Voucher\Contracts\RedeemsVouchers;
use LBHurtado\Voucher\Contracts\SettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Exceptions\SettlementEnvelopeNotReadyException;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Voucher\Services\SettlementEnvelopeExecutionDriver;

it('executes a settlement-envelope authority voucher', function () {
    $gateway = new FakeSettlementEnvelopeGateway([
        'children' => [
            ['cash' => ['amount' => 100, 'currency' => 'PHP']],
        ],
    ]);
    $generator = new FakeSettlementEnvelopeVoucherGenerator;
    $redeemer = new FakeSettlementEnvelopeVoucherRedeemer;

    $result = (new SettlementEnvelopeExecutionDriver($gateway, $generator, $redeemer))->execute(settlementEnvelopeContext());

    expect($result)->toBeInstanceOf(ExecutionResultData::class)
        ->and($result->successful)->toBeTrue()
        ->and($result->driver)->toBe('settlement_envelope')
        ->and($result->metadata['envelope_reference'])->toBe('ENV-123')
        ->and($result->metadata['locked'])->toBeTrue()
        ->and($result->metadata['child_count'])->toBe(1)
        ->and($gateway->calls)->toBe(['load', 'assertReady', 'lock', 'childVoucherInstructions']);
});

it('loads the configured settlement envelope', function () {
    $gateway = new FakeSettlementEnvelopeGateway;

    (new SettlementEnvelopeExecutionDriver(
        gateway: $gateway,
        vouchers: new FakeSettlementEnvelopeVoucherGenerator,
        redeemer: new FakeSettlementEnvelopeVoucherRedeemer,
    ))->execute(settlementEnvelopeContext(envelopeReference: 'ENV-999'));

    expect($gateway->loadedReferences)->toBe(['ENV-999']);
});

it('verifies settlement envelope gates before execution', function () {
    $gateway = new FakeSettlementEnvelopeGateway(ready: false);
    $generator = new FakeSettlementEnvelopeVoucherGenerator;

    $result = (new SettlementEnvelopeExecutionDriver(
        gateway: $gateway,
        vouchers: $generator,
        redeemer: new FakeSettlementEnvelopeVoucherRedeemer,
    ))->execute(settlementEnvelopeContext());

    expect($result->successful)->toBeFalse()
        ->and($result->failure)->toBe('settlement_envelope_not_ready')
        ->and($gateway->calls)->toBe(['load', 'assertReady'])
        ->and($generator->calls)->toBe([]);
});

it('locks the settlement envelope before generating child vouchers', function () {
    $order = [];
    $gateway = new FakeSettlementEnvelopeGateway([
        'children' => [
            ['cash' => ['amount' => 100, 'currency' => 'PHP']],
        ],
    ]);
    $gateway->recordTo($order);

    $generator = new FakeSettlementEnvelopeVoucherGenerator;
    $generator->recordTo($order);

    (new SettlementEnvelopeExecutionDriver(
        gateway: $gateway,
        vouchers: $generator,
        redeemer: new FakeSettlementEnvelopeVoucherRedeemer,
    ))->execute(settlementEnvelopeContext());

    expect(array_search('lock', $order, true))->toBeLessThan(array_search('generate', $order, true));
});

it('generates child vouchers from settlement envelope entries', function () {
    $generator = new FakeSettlementEnvelopeVoucherGenerator;

    $result = (new SettlementEnvelopeExecutionDriver(
        gateway: new FakeSettlementEnvelopeGateway([
            'children' => [
                ['cash' => ['amount' => 100, 'currency' => 'PHP']],
                ['cash' => ['amount' => 200, 'currency' => 'PHP']],
            ],
        ]),
        vouchers: $generator,
        redeemer: new FakeSettlementEnvelopeVoucherRedeemer,
    ))->execute(settlementEnvelopeContext());

    expect($generator->generatedInstructions)->toHaveCount(2)
        ->and($result->metadata['child_vouchers'])->toBe(['CHILD-1', 'CHILD-2']);
});

it('auto-redeems child vouchers when configured', function () {
    $redeemer = new FakeSettlementEnvelopeVoucherRedeemer;

    (new SettlementEnvelopeExecutionDriver(
        gateway: new FakeSettlementEnvelopeGateway([
            'children' => [
                ['cash' => ['amount' => 100, 'currency' => 'PHP']],
            ],
        ]),
        vouchers: new FakeSettlementEnvelopeVoucherGenerator,
        redeemer: $redeemer,
    ))->execute(settlementEnvelopeContext(autoRedeemChildren: true));

    expect($redeemer->redeemedCodes)->toBe(['CHILD-1']);
});

it('falls back failed child executions to claim vouchers when configured', function () {
    $generator = new FakeSettlementEnvelopeVoucherGenerator;
    $redeemer = new FakeSettlementEnvelopeVoucherRedeemer(['CHILD-1' => false]);

    $result = (new SettlementEnvelopeExecutionDriver(
        gateway: new FakeSettlementEnvelopeGateway([
            'children' => [
                ['cash' => ['amount' => 100, 'currency' => 'PHP']],
            ],
            'fallback' => [
                'cash' => ['amount' => 100, 'currency' => 'PHP'],
                'metadata' => ['flow_type' => 'claim'],
            ],
        ]),
        vouchers: $generator,
        redeemer: $redeemer,
    ))->execute(settlementEnvelopeContext(autoRedeemChildren: true, fallbackToClaim: true));

    expect($result->successful)->toBeTrue()
        ->and($generator->generatedInstructions)->toHaveCount(2)
        ->and($result->metadata['fallback_claim_vouchers'])->toBe(['CHILD-2'])
        ->and($result->events)->toContain('settlement_envelope.child_fallback_generated');
});

it('registers the settlement envelope driver without changing default resolution', function () {
    $registry = app(ExecutionDriverRegistry::class);

    expect($registry->resolve('settlement_envelope'))->toBeInstanceOf(SettlementEnvelopeExecutionDriver::class)
        ->and($registry->resolve('default')->key())->toBe('default');
});

it('executes settlement envelope instructions through the registry', function () {
    app()->instance(SettlementEnvelopeExecutionGateway::class, new FakeSettlementEnvelopeGateway([
        'children' => [
            ['cash' => ['amount' => 100, 'currency' => 'PHP']],
        ],
    ]));
    app()->instance(GeneratesVouchers::class, new FakeSettlementEnvelopeVoucherGenerator);
    app()->instance(RedeemsVouchers::class, new FakeSettlementEnvelopeVoucherRedeemer);

    $result = app(ExecutionEngine::class)->execute(settlementEnvelopeContext());

    expect($result->successful)->toBeTrue()
        ->and($result->driver)->toBe('settlement_envelope');
});

function settlementEnvelopeContext(
    string $envelopeReference = 'ENV-123',
    bool $autoRedeemChildren = false,
    bool $fallbackToClaim = false,
): ExecutionContextData {
    return new ExecutionContextData(
        contact: new Contact(['mobile' => '+639171234567']),
        voucherCode: 'AUTHORITY-1',
        instruction: ExecutionInstructionData::from([
            'driver' => 'settlement_envelope',
            'metadata' => [
                'envelope_reference' => $envelopeReference,
                'auto_redeem_children' => $autoRedeemChildren,
                'fallback_to_claim' => $fallbackToClaim,
            ],
        ]),
    );
}

class FakeSettlementEnvelopeGateway implements SettlementEnvelopeExecutionGateway
{
    public array $calls = [];

    public array $loadedReferences = [];

    private ?array $order = null;

    public function __construct(
        public array $envelope = [],
        public bool $ready = true,
    ) {}

    public function recordTo(array &$order): void
    {
        $this->order = &$order;
    }

    public function load(ExecutionContextData $context): mixed
    {
        $this->calls[] = 'load';
        $this->loadedReferences[] = (string) $context->instruction?->metadata['envelope_reference'];

        return ['reference' => $context->instruction?->metadata['envelope_reference']] + $this->envelope;
    }

    public function assertReady(mixed $envelope, ExecutionContextData $context): void
    {
        $this->calls[] = 'assertReady';

        if (! $this->ready) {
            throw new SettlementEnvelopeNotReadyException('Envelope is not settleable.');
        }
    }

    public function lock(mixed $envelope, ExecutionContextData $context): mixed
    {
        $this->calls[] = 'lock';
        $this->order[] = 'lock';

        return $envelope + ['locked' => true];
    }

    public function childVoucherInstructions(mixed $envelope, ExecutionContextData $context): array
    {
        $this->calls[] = 'childVoucherInstructions';

        return $envelope['children'] ?? [];
    }

    public function claimFallbackInstructions(mixed $envelope, array $childInstruction, ExecutionContextData $context): ?array
    {
        $this->calls[] = 'claimFallbackInstructions';

        return $envelope['fallback'] ?? null;
    }
}

class FakeSettlementEnvelopeVoucherGenerator implements GeneratesVouchers
{
    public array $calls = [];

    public array $generatedInstructions = [];

    public int $sequence = 0;

    private ?array $order = null;

    public function recordTo(array &$order): void
    {
        $this->order = &$order;
    }

    public function handle(\LBHurtado\Voucher\Data\VoucherInstructionsData|array $instructions): Collection
    {
        $this->calls[] = 'generate';
        $this->order[] = 'generate';
        $this->generatedInstructions[] = $instructions;
        $this->sequence++;

        return collect([(object) ['code' => "CHILD-{$this->sequence}"]]);
    }
}

class FakeSettlementEnvelopeVoucherRedeemer implements RedeemsVouchers
{
    public array $redeemedCodes = [];

    public function __construct(
        public array $results = [],
    ) {}

    public function handle(Contact $contact, string $voucher_code, array $meta = []): bool
    {
        $this->redeemedCodes[] = $voucher_code;

        return $this->results[$voucher_code] ?? true;
    }
}
