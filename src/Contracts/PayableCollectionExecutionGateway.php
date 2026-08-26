<?php

namespace LBHurtado\Voucher\Contracts;

use LBHurtado\Voucher\Data\ExecutionContextData;

interface PayableCollectionExecutionGateway
{
    /**
     * @return array<string, mixed>
     */
    public function authorize(ExecutionContextData $context, string $executionId): array;

    /**
     * @return array<string, mixed>
     */
    public function credit(
        ExecutionContextData $context,
        int $amountMinor,
        string $providerTransactionId,
        string $executionId,
    ): array;
}
