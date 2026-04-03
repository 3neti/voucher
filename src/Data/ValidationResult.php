<?php

namespace LBHurtado\Voucher\Data;

class ValidationResult
{
    public function __construct(
        public readonly bool $passes,
        public readonly array $failures = [],
    ) {}

    public function failed(): bool
    {
        return ! $this->passes;
    }
}
