<?php

declare(strict_types=1);

namespace VoucherV2Experiment;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class SettlementData extends Data
{
    public function __construct(public string $driver, public string $version, public string $readiness_gate = 'settleable', public ?string $payment_requirement = null) {}
}

final class ValueFlowData extends Data
{
    public function __construct(public string $destination) {}
}

final class EventData extends Data
{
    /** @param array<int,string> $requires */
    public function __construct(
        public string $actor,
        public array $requires = [],
        public ?string $surface = null,
        public ?string $authentication = null,
    ) {}
}

final class ExecutionData extends Data
{
    /** @param array<string,EventData> $events */
    public function __construct(public string $schema, public string $driver, public ?SettlementData $settlement, public ?ValueFlowData $value_flow, public array $events) {}
}

final class Contract
{
    /** @param array<string,mixed> $input */
    public static function parse(array $input): ExecutionData|array
    {
        $schema = $input['schema'] ?? 'voucher.execution.v1';
        if ($schema === 'voucher.execution.v1') {
            return $input + ['schema' => $schema];
        }
        if ($schema !== 'voucher.execution.v2') {
            throw new InvalidArgumentException('Unsupported execution schema.');
        }
        Validator::make($input, [
            'schema' => ['required', 'in:voucher.execution.v2'],
            'driver' => ['required', 'string', 'in:settlement_envelope,disburse_then_repay'],
            'settlement' => ['required', 'array'],
            'settlement.driver' => ['required', 'string', 'min:1'],
            'settlement.version' => ['required', 'string', 'min:1'],
            'settlement.readiness_gate' => ['required', 'string', 'min:1'],
            'settlement.payment_requirement' => ['nullable', 'in:linked_confirmed_payment'],
            'value_flow' => ['required', 'array'],
            'value_flow.destination' => ['required', 'string', 'min:1'],
            'events' => ['required', 'array', 'min:1'],
            'events.*.actor' => ['required', 'string', 'in:beneficiary,hospital_reviewer,payer,system'],
            'events.*.requires' => ['sometimes', 'array'],
            'events.*.requires.*' => ['string'],
            'events.*.surface' => ['nullable', 'in:claim,pay'],
            'events.*.authentication' => ['nullable', 'in:anonymous,required,optional'],
        ])->validate();
        $events = [];
        foreach ($input['events'] as $key => $value) {
            $events[$key] = new EventData($value['actor'], $value['requires'] ?? [], $value['surface'] ?? null, $value['authentication'] ?? null);
        }
        $settlement = $input['settlement'];

        return new ExecutionData($schema, $input['driver'], new SettlementData($settlement['driver'], $settlement['version'], $settlement['readiness_gate'], $settlement['payment_requirement'] ?? null), new ValueFlowData($input['value_flow']['destination']), $events);
    }
}

final class EventCatalog
{
    /** @param array<string,array{label:string,channels:array<int,string>}> $events */
    public function __construct(private readonly array $events) {}

    /** @param array<int,string> $keys */
    public function validate(array $keys): void
    {
        foreach ($keys as $key) {
            if (! isset($this->events[$key]) || ! in_array('webhook', $this->events[$key]['channels'], true)) {
                throw new InvalidArgumentException('Unsupported webhook event.');
            }
        }
    }

    public function label(string $key): string
    {
        return $this->events[$key]['label'] ?? throw new InvalidArgumentException('Unknown event.');
    }
}

/** Proposed campaign snapshot and preview only; not a production compiler. */
final class CampaignTemplate
{
    public readonly string $definitionHash;

    /** @param array<string,mixed> $definition */
    public function __construct(public readonly string $id, public readonly string $version, public readonly array $definition)
    {
        $this->definitionHash = hash('sha256', json_encode($definition, JSON_THROW_ON_ERROR));
    }

    /** @return array<string,mixed> */
    public function compile(int $targetMinor, string $currency): array
    {
        if ($targetMinor <= 0 || $currency !== 'PHP') {
            throw new InvalidArgumentException('Invalid campaign price.');
        }
        $execution = Contract::parse($this->definition['execution']);
        if (! $execution instanceof ExecutionData || $execution->settlement?->payment_requirement !== 'linked_confirmed_payment') {
            throw new InvalidArgumentException('Campaign template requires linked confirmed payment.');
        }

        return ['voucher_type' => 'settlement', 'cash' => ['amount' => 0, 'currency' => $currency], 'target_amount' => $targetMinor / 100, 'execution' => $execution->toArray(), 'template' => ['id' => $this->id, 'version' => $this->version, 'definition_hash' => $this->definitionHash]];
    }

    /** @param array<string,mixed> $instruction
     * @return array<string,mixed>
     */
    public static function preview(string $code, array $instruction): array
    {
        return ['engineering' => $instruction, 'screens' => [['role' => 'beneficiary', 'code' => $code, 'event' => 'complete_application'], ['role' => 'system', 'code' => $code, 'event' => 'complete_product']]];
    }
}
