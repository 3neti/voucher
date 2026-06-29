<?php

namespace LBHurtado\Voucher\Services;

use LBHurtado\Voucher\Contracts\StoredValueExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;

class NullStoredValueExecutionGateway implements StoredValueExecutionGateway
{
    public function activate(ExecutionContextData $context, string $executionId): array
    {
        return $this->state($context);
    }

    public function spend(ExecutionContextData $context, int $amount, string $executionId): array
    {
        throw new StoredValueSpendRejectedException('No stored value execution gateway is configured.');
    }

    public function replenish(ExecutionContextData $context, int $amount, string $executionId): array
    {
        throw new StoredValueSpendRejectedException('No stored value execution gateway is configured.');
    }

    public function balance(ExecutionContextData $context): int
    {
        return (int) ($context->instruction?->metadata['initial_balance'] ?? $context->instruction?->metadata['max_balance'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(ExecutionContextData $context): array
    {
        return [
            'stored_value_reference' => $context->instruction?->metadata['stored_value_reference'] ?? $context->voucherCode,
            'owner_mobile' => $context->contact->mobile,
            'remaining_balance' => $this->balance($context),
        ];
    }
}
