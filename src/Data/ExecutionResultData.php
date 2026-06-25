<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

class ExecutionResultData extends Data
{
    public function __construct(
        public bool $successful,
        public string $status,
        public string $driver,
        public array $events = [],
        public ?string $failure = null,
        public array $providerReferences = [],
        public array $reconciliation = [],
        public array $children = [],
        public array $metadata = [],
    ) {}

    public static function succeeded(string $driver, array $metadata = []): self
    {
        return new self(
            successful: true,
            status: 'succeeded',
            driver: $driver,
            metadata: $metadata,
        );
    }

    public static function failed(string $driver, string $failure, array $metadata = []): self
    {
        return new self(
            successful: false,
            status: 'failed',
            driver: $driver,
            failure: $failure,
            metadata: $metadata,
        );
    }
}
