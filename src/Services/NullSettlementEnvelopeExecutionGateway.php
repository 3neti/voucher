<?php

namespace LBHurtado\Voucher\Services;

use LBHurtado\Voucher\Contracts\SettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\SettlementEnvelopeNotReadyException;

class NullSettlementEnvelopeExecutionGateway implements SettlementEnvelopeExecutionGateway
{
    public function load(ExecutionContextData $context): mixed
    {
        return [
            'reference' => $context->instruction?->metadata['envelope_reference'] ?? null,
        ];
    }

    public function assertReady(mixed $envelope, ExecutionContextData $context): void
    {
        throw new SettlementEnvelopeNotReadyException('No settlement envelope execution gateway is configured.');
    }

    public function lock(mixed $envelope, ExecutionContextData $context): mixed
    {
        return $envelope;
    }

    public function childVoucherInstructions(mixed $envelope, ExecutionContextData $context): array
    {
        return [];
    }

    public function claimFallbackInstructions(mixed $envelope, array $childInstruction, ExecutionContextData $context): ?array
    {
        return null;
    }
}
