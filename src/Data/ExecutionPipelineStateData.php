<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

class ExecutionPipelineStateData extends Data
{
    public function __construct(
        public ExecutionContextData $context,
        public string $driver,
        public string $executionId,
        public array $events = [],
        public array $metadata = [],
        public ?ExecutionResultData $result = null,
    ) {}

    public static function forContext(
        ExecutionContextData $context,
        string $driver,
        string $executionId,
        array $events = [],
        array $metadata = [],
    ): self {
        return new self(
            context: $context,
            driver: $driver,
            executionId: $executionId,
            events: $events,
            metadata: $metadata,
        );
    }
}
