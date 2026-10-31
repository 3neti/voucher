<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Exceptions\UnsupportedExecutionInstructionSchemaException;
use Spatie\LaravelData\Data;

class ExecutionInstructionData extends Data
{
    public const SCHEMA = 'voucher.execution.v1';

    public const SCHEMA_V2 = 'voucher.execution.v2';

    public function __construct(
        public string $schema = self::SCHEMA,
        public string $driver = 'default',
        public ?string $mode = null,
        public ?array $pipeline = null,
        public ?string $fallback = null,
        public ?array $visibility = null,
        public ?array $metadata = null,
        public ?array $settlement = null,
        public ?array $value_flow = null,
        public ?array $events = null,
    ) {
        if (! in_array($this->schema, [self::SCHEMA, self::SCHEMA_V2], true)) {
            throw UnsupportedExecutionInstructionSchemaException::forSchema($this->schema);
        }
    }

    public static function rules(): array
    {
        return [
            'schema' => ['nullable', 'string', 'min:1'],
            'driver' => ['nullable', 'string', 'min:1'],
            'mode' => ['nullable', 'string', 'min:1'],
            'pipeline' => ['nullable', 'array'],
            'pipeline.*' => ['string', 'min:1'],
            'fallback' => ['nullable', 'string', 'min:1'],
            'visibility' => ['nullable', 'array'],
            'visibility.*' => ['string', 'min:1'],
            'metadata' => ['nullable', 'array'],
            'settlement' => ['nullable', 'array'],
            'value_flow' => ['nullable', 'array'],
            'events' => ['nullable', 'array'],
        ];
    }

    public function settlementData(): ?ExecutionSettlementData
    {
        return $this->schema === self::SCHEMA_V2 && $this->settlement !== null
            ? ExecutionSettlementData::from($this->settlement)
            : null;
    }

    public function valueFlowData(): ?ExecutionValueFlowData
    {
        return $this->schema === self::SCHEMA_V2 && $this->value_flow !== null
            ? ExecutionValueFlowData::from($this->value_flow)
            : null;
    }

    /** @return array<string, ExecutionEventData> */
    public function eventData(): array
    {
        if ($this->schema !== self::SCHEMA_V2 || $this->events === null) {
            return [];
        }

        $events = [];
        foreach ($this->events as $key => $event) {
            $events[$key] = ExecutionEventData::from($event);
        }

        return $events;
    }
}
