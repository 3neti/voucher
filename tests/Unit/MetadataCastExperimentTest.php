<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;
use LBHurtado\XChange\Services\Execution\XChangeSettlementEnvelopeExecutionGateway;
use LBHurtado\XChange\Services\SettlementCollectionGate;
use Spatie\LaravelData\Attributes\WithCastAndTransformer;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

final class ExperimentSettlementEnvelopeData extends Data
{
    public function __construct(
        public ?string $driver,
        public ?string $readiness_gate,
        public array $original,
    ) {}

    public static function fromRaw(array $raw): self
    {
        return new self($raw['driver'] ?? null, $raw['readiness_gate'] ?? null, $raw);
    }
}

final class ExperimentMetadataCast implements Cast, Transformer
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if (! is_array($value) || ! is_array($value['settlement_envelope'] ?? null)) {
            return $value;
        }

        $envelope = $value['settlement_envelope'];
        if ((isset($envelope['driver']) && ! is_string($envelope['driver'])) ||
            (isset($envelope['readiness_gate']) && ! is_string($envelope['readiness_gate']))) {
            return $value;
        }

        $value['settlement_envelope'] = ExperimentSettlementEnvelopeData::fromRaw($envelope);

        return $value;
    }

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        if (is_array($value) && ($value['settlement_envelope'] ?? null) instanceof ExperimentSettlementEnvelopeData) {
            $value['settlement_envelope'] = $value['settlement_envelope']->original;
        }

        return $value;
    }
}

final class ExperimentExecutionData extends Data
{
    public function __construct(
        public string $schema = 'voucher.execution.v1',
        public string $driver = 'default',
        #[WithCastAndTransformer(ExperimentMetadataCast::class)]
        public ?array $metadata = null,
    ) {}

    public static function rules(): array
    {
        return [
            'metadata' => ['nullable', 'array'],
            'metadata.settlement_envelope' => ['nullable', 'array'],
            'metadata.settlement_envelope.driver' => ['nullable', 'string'],
            'metadata.settlement_envelope.readiness_gate' => ['nullable', 'string'],
        ];
    }
}

function experimentPayload(?array $metadata): array
{
    return ['schema' => 'voucher.execution.v1', 'driver' => 'settlement_envelope', 'metadata' => $metadata];
}

it('keeps the baseline execution metadata as arrays across construction and serialization', function () {
    $payload = experimentPayload(['settlement_envelope' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable'], 'value_flow' => ['destination' => 'account.mobile:09171234567.client_funds']]);
    $data = ExecutionInstructionData::from($payload);
    expect($data->metadata)->toBe($payload['metadata'])
        ->and(data_get($data->metadata, 'settlement_envelope.driver'))->toBe('philhealth-bst')
        ->and($data->all()['metadata'])->toBe($payload['metadata'])
        ->and($data->toArray()['metadata'])->toBe($payload['metadata'])
        ->and(json_decode($data->toJson(), true)['metadata'])->toBe($payload['metadata']);
});

it('casts the envelope but round trips the full original metadata', function () {
    $metadata = ['settlement_envelope' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable', 'custom_gate' => ['version' => 2]], 'value_flow' => ['destination' => 'account.mobile:09171234567.client_funds'], 'other_driver' => ['keep' => true]];
    $data = ExperimentExecutionData::from(experimentPayload($metadata));
    expect($data->metadata['settlement_envelope'])->toBeInstanceOf(ExperimentSettlementEnvelopeData::class)
        ->and($data->metadata['settlement_envelope']->driver)->toBe('philhealth-bst')
        ->and($data->toArray()['metadata'])->toBe($metadata)
        ->and(json_decode($data->toJson(), true)['metadata'])->toBe($metadata)
        ->and(ExperimentExecutionData::from($data->toArray())->toArray()['metadata'])->toBe($metadata);
});

it('preserves absent null and malformed envelope entries on round trip', function (?array $metadata) {
    $data = ExperimentExecutionData::factory()->withoutValidation()->from(experimentPayload($metadata));
    expect($data->toArray()['metadata'])->toBe($metadata);
})->with([
    'null metadata' => [null],
    'missing envelope' => [['value_flow' => ['destination' => 'somewhere']]],
    'null envelope' => [['settlement_envelope' => null]],
    'empty envelope' => [['settlement_envelope' => []]],
    'explicit null fields' => [['settlement_envelope' => ['driver' => null, 'readiness_gate' => null]]],
    'missing fields' => [['settlement_envelope' => ['custom' => true]]],
    'non-array envelope' => [['settlement_envelope' => 'legacy']],
    'invalid driver' => [['settlement_envelope' => ['driver' => ['invalid']]]],
]);

it('exposes the runtime consumer break and direct construction difference', function () {
    $metadata = ['settlement_envelope' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable']];
    $from = ExperimentExecutionData::from(experimentPayload($metadata));
    $direct = new ExperimentExecutionData(driver: 'settlement_envelope', metadata: $metadata);
    $envelope = data_get($from->metadata, 'settlement_envelope', []);
    $gatewayMetadata = is_array($envelope) ? $envelope : [];
    expect(data_get($from->metadata, 'settlement_envelope.driver'))->toBe('philhealth-bst')
        ->and($from->all()['metadata']['settlement_envelope'])->toBeInstanceOf(ExperimentSettlementEnvelopeData::class)
        ->and($gatewayMetadata)->toBe([])
        ->and($gatewayMetadata['driver'] ?? 'fallback')->toBe('fallback')
        ->and($direct->metadata['settlement_envelope'])->toBeArray();
});

it('requires explicit validation to reject a malformed nested driver', function () {
    $payload = experimentPayload(['settlement_envelope' => ['driver' => ['not-a-string']]]);
    expect(ExperimentExecutionData::factory()->withoutValidation()->from($payload)->metadata)->toBe($payload['metadata']);
    expect(fn () => ExperimentExecutionData::validateAndCreate($payload))->toThrow(ValidationException::class);
});

it('exercises the real x-change execution gateway with baseline and cast metadata', function () {
    require_once '/Users/rli/PhpstormProjects/x-change-sandbox/vendor/3neti/x-change/src/Contracts/SettlementEnvelopeReadinessContract.php';
    require_once '/Users/rli/PhpstormProjects/x-change-sandbox/vendor/3neti/x-change/src/Services/Execution/XChangeSettlementEnvelopeExecutionGateway.php';
    $readiness = Mockery::mock(SettlementEnvelopeReadinessContract::class);
    $gateway = new XChangeSettlementEnvelopeExecutionGateway($readiness);
    $metadata = ['settlement_envelope' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'custom-gate', 'reference' => 'ENV-123']];
    $cast = ExperimentExecutionData::from(experimentPayload($metadata));
    $baselineContext = new ExecutionContextData(contact: null, voucherCode: 'EX1', instruction: new ExecutionInstructionData(metadata: $metadata));
    $castContext = new ExecutionContextData(contact: null, voucherCode: 'EX1', instruction: new ExecutionInstructionData(metadata: $cast->metadata));
    expect($gateway->load($baselineContext)['readiness_gate'])->toBe('custom-gate')
        ->and($gateway->load($baselineContext)['reference'])->toBe('ENV-123')
        ->and($gateway->load($castContext)['readiness_gate'])->toBe('settleable')
        ->and($gateway->load($castContext)['reference'])->toBe('EX1');
});

it('exercises the collection gate against the persisted instruction and evidence paths', function () {
    require_once '/Users/rli/PhpstormProjects/x-change-sandbox/vendor/3neti/x-change/src/Services/SettlementCollectionGate.php';
    config()->set('x-change.settlement.default_driver', 'fallback-driver');
    config()->set('x-change.settlement.default_gate', 'settleable');
    $gate = (new ReflectionClass(SettlementCollectionGate::class))->newInstanceWithoutConstructor();
    $voucher = new Voucher;
    $voucher->setRawAttributes(['instructions' => json_encode(['metadata' => []]), 'metadata' => json_encode(['instructions' => ['execution' => ['metadata' => ['settlement_envelope' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'custom-gate']]]]])]);
    $context = $gate->contextFromVoucher($voucher);
    expect($context['driver'])->toBe('fallback-driver')->and($context['gate'])->toBe('settleable');
    $voucher->setRawAttributes(['instructions' => json_encode(['metadata' => []]), 'metadata' => json_encode(['settlement_envelope' => ['driver' => 'philhealth-bst', 'checklist' => ['amount_verified' => true]]])]);
    $context = $gate->contextFromVoucher($voucher);
    expect($context['driver'])->toBe('philhealth-bst')->and($context['checklist']['amount_verified'])->toBeTrue();
});

it('exposes that changing a typed property is lost by a raw-preserving transformer', function () {
    $metadata = ['settlement_envelope' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable']];
    $data = ExperimentExecutionData::from(experimentPayload($metadata));
    $data->metadata['settlement_envelope']->readiness_gate = 'new-gate';
    expect($data->metadata['settlement_envelope']->readiness_gate)->toBe('new-gate')
        ->and($data->toArray()['metadata']['settlement_envelope']['readiness_gate'])->toBe('settleable');
});
