<?php

namespace LBHurtado\Voucher\Data;

use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

class ExecutionResultData extends Data
{
    public function __construct(
        public ?string $execution_id,
        public bool $successful,
        public string $status,
        public string $driver,
        public array $events = [],
        public ?string $failure = null,
        public array $providerReferences = [],
        public array $reconciliation = [],
        public array $children = [],
        public array $metadata = [],
    ) {
        $this->execution_id ??= (string) Str::uuid();
    }

    public static function succeeded(string $driver, array $metadata = []): self
    {
        return new self(
            execution_id: null,
            successful: true,
            status: 'succeeded',
            driver: $driver,
            metadata: $metadata,
        );
    }

    public static function failed(string $driver, string $failure, array $metadata = []): self
    {
        return new self(
            execution_id: null,
            successful: false,
            status: 'failed',
            driver: $driver,
            failure: $failure,
            metadata: $metadata,
        );
    }
}
