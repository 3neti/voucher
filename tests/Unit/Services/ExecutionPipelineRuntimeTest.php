<?php

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Contracts\ExecutionPipelineStepContract;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Data\ExecutionPipelineStateData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Exceptions\UnknownExecutionPipelineStepException;
use LBHurtado\Voucher\Services\ExecutionPipelineRuntime;
use LBHurtado\Voucher\Services\ExecutionPipelineStepRegistry;

it('registers execution pipeline steps by key', function () {
    $registry = new ExecutionPipelineStepRegistry(app());
    $step = new FakePipelineStep('validate_contract');

    $registry->register('validate_contract', $step);

    expect($registry->has('validate_contract'))->toBeTrue()
        ->and($registry->resolve('validate_contract'))->toBe($step)
        ->and($registry->keys())->toBe(['validate_contract']);
});

it('throws a clear exception for unknown execution pipeline steps', function () {
    $registry = new ExecutionPipelineStepRegistry(app());

    expect(fn () => $registry->resolve('verify_balance'))
        ->toThrow(UnknownExecutionPipelineStepException::class, 'Unknown execution pipeline step [verify_balance].');
});

it('executes driver-composed pipeline steps in order', function () {
    $calls = [];
    $registry = new ExecutionPipelineStepRegistry(app());
    $registry
        ->register('validate_contract', new FakePipelineStep('validate_contract', $calls))
        ->register('verify_balance', new FakePipelineStep('verify_balance', $calls))
        ->register('reconcile', new FakePipelineStep('reconcile', $calls));

    $state = (new ExecutionPipelineRuntime($registry))->run(
        ExecutionPipelineStateData::forContext(pipelineContext(), 'composed_driver', 'EXEC-1'),
        ['validate_contract', 'verify_balance', 'reconcile'],
    );

    expect($calls)->toBe(['validate_contract', 'verify_balance', 'reconcile'])
        ->and($state->events)->toBe([
            'pipeline.validate_contract',
            'pipeline.verify_balance',
            'pipeline.reconcile',
        ]);
});

it('resolves class-string and closure pipeline steps through the container', function () {
    app()->instance(FakePipelineCallRecorder::class, new FakePipelineCallRecorder);

    $registry = new ExecutionPipelineStepRegistry(app());
    $registry
        ->register('container_step', FakeContainerResolvedPipelineStep::class)
        ->register('closure_step', fn (): ExecutionPipelineStepContract => new FakePipelineStep('closure_step'));

    $state = (new ExecutionPipelineRuntime($registry))->run(
        ExecutionPipelineStateData::forContext(pipelineContext(), 'composed_driver', 'EXEC-1'),
        ['container_step', 'closure_step'],
    );

    expect(app(FakePipelineCallRecorder::class)->calls)->toBe(['container_step'])
        ->and($state->events)->toContain('pipeline.container_step', 'pipeline.closure_step');
});

it('short-circuits later steps when a step finalizes the execution result', function () {
    $calls = [];
    $registry = new ExecutionPipelineStepRegistry(app());
    $registry
        ->register('verify_balance', new FakeFinalizingPipelineStep('verify_balance', $calls))
        ->register('disburse', new FakePipelineStep('disburse', $calls));

    $state = (new ExecutionPipelineRuntime($registry))->run(
        ExecutionPipelineStateData::forContext(pipelineContext(), 'composed_driver', 'EXEC-1'),
        ['verify_balance', 'disburse'],
    );

    expect($calls)->toBe(['verify_balance'])
        ->and($state->result)->toBeInstanceOf(ExecutionResultData::class)
        ->and($state->result->successful)->toBeFalse()
        ->and($state->result->failure)->toBe('verify_balance_failed');
});

it('allows a driver to assemble modular execution pipelines', function () {
    $registry = new ExecutionPipelineStepRegistry(app());
    $registry
        ->register('validate_contract', new FakePipelineStep('validate_contract'))
        ->register('verify_balance', new FakePipelineStep('verify_balance'))
        ->register('reconcile', new FakePipelineStep('reconcile'));

    $driver = new FakeComposedExecutionDriver(new ExecutionPipelineRuntime($registry));

    $result = $driver->execute(pipelineContext([
        'pipeline' => ['validate_contract', 'verify_balance', 'reconcile'],
    ]));

    expect($result->successful)->toBeTrue()
        ->and($result->driver)->toBe('composed_driver')
        ->and($result->events)->toBe([
            'pipeline.validate_contract',
            'pipeline.verify_balance',
            'pipeline.reconcile',
        ])
        ->and($result->metadata['pipeline'])->toBe(['validate_contract', 'verify_balance', 'reconcile']);
});

it('resolves the singleton execution pipeline step registry from the container', function () {
    expect(app(ExecutionPipelineStepRegistry::class))
        ->toBe(app(ExecutionPipelineStepRegistry::class));
});

function pipelineContext(array $metadata = []): ExecutionContextData
{
    return new ExecutionContextData(
        contact: new Contact(['mobile' => '+639171234567']),
        voucherCode: 'PIPE-1',
        instruction: ExecutionInstructionData::from([
            'driver' => 'composed_driver',
            'metadata' => $metadata,
        ]),
    );
}

class FakePipelineStep implements ExecutionPipelineStepContract
{
    public function __construct(
        private readonly string $key,
        private array &$calls = [],
    ) {}

    public function handle(ExecutionPipelineStateData $state, Closure $next): ExecutionPipelineStateData
    {
        $this->calls[] = $this->key;
        $state->events[] = "pipeline.{$this->key}";

        return $next($state);
    }
}

class FakeFinalizingPipelineStep implements ExecutionPipelineStepContract
{
    public function __construct(
        private readonly string $key,
        private array &$calls = [],
    ) {}

    public function handle(ExecutionPipelineStateData $state, Closure $next): ExecutionPipelineStateData
    {
        $this->calls[] = $this->key;
        $state->events[] = "pipeline.{$this->key}";
        $state->result = new ExecutionResultData(
            execution_id: $state->executionId,
            successful: false,
            status: 'failed',
            driver: $state->driver,
            events: $state->events,
            failure: "{$this->key}_failed",
            metadata: $state->metadata,
        );

        return $next($state);
    }
}

class FakeContainerResolvedPipelineStep implements ExecutionPipelineStepContract
{
    public function __construct(
        private readonly FakePipelineCallRecorder $recorder,
    ) {}

    public function handle(ExecutionPipelineStateData $state, Closure $next): ExecutionPipelineStateData
    {
        $this->recorder->calls[] = 'container_step';
        $state->events[] = 'pipeline.container_step';

        return $next($state);
    }
}

class FakePipelineCallRecorder
{
    public array $calls = [];
}

class FakeComposedExecutionDriver implements ExecutionDriverContract
{
    public function __construct(
        private readonly ExecutionPipelineRuntime $pipeline,
    ) {}

    public function key(): string
    {
        return 'composed_driver';
    }

    public function execute(ExecutionContextData $context): ExecutionResultData
    {
        $steps = $context->instruction?->metadata['pipeline'] ?? [];
        $state = $this->pipeline->run(
            ExecutionPipelineStateData::forContext($context, $this->key(), 'EXEC-1'),
            $steps,
        );

        return $state->result ?? new ExecutionResultData(
            execution_id: $state->executionId,
            successful: true,
            status: 'succeeded',
            driver: $this->key(),
            events: $state->events,
            metadata: $state->metadata + [
                'pipeline' => $steps,
            ],
        );
    }
}
