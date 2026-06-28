<?php

namespace LBHurtado\Voucher\Services;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;

class ExecutionEngine
{
    public function __construct(
        private readonly ExecutionDriverRegistry $drivers,
    ) {}

    public function driverKeyFor(ExecutionContextData $context): string
    {
        return $context->instruction?->driver ?: 'default';
    }

    public function execute(ExecutionContextData $context): ExecutionResultData
    {
        return $this->drivers
            ->resolve($this->driverKeyFor($context))
            ->execute($context);
    }
}
