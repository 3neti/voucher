<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Exceptions\UnsupportedExecutionInstructionSchemaException;
use Spatie\LaravelData\Data;

class ExecutionInstructionData extends Data
{
    public const SCHEMA = 'voucher.execution.v1';

    public function __construct(
        public string $schema = self::SCHEMA,
        public string $driver = 'default',
        public ?string $mode = null,
        public ?array $pipeline = null,
        public ?string $fallback = null,
        public ?array $visibility = null,
        public ?array $metadata = null,
    ) {
        if ($this->schema !== self::SCHEMA) {
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
        ];
    }
}
