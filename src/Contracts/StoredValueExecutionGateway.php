<?php

namespace LBHurtado\Voucher\Contracts;

use LBHurtado\Voucher\Data\ExecutionContextData;

interface StoredValueExecutionGateway
{
    /**
     * @return array<string, mixed>
     */
    public function activate(ExecutionContextData $context, string $executionId): array;

    /**
     * @return array<string, mixed>
     */
    public function spend(ExecutionContextData $context, int $amount, string $executionId): array;

    /**
     * @return array<string, mixed>
     */
    public function replenish(ExecutionContextData $context, int $amount, string $executionId): array;

    public function balance(ExecutionContextData $context): int;
}
