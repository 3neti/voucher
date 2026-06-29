<?php

namespace LBHurtado\Voucher\Contracts;

use LBHurtado\Voucher\Data\ExecutionContextData;

interface SettlementEnvelopeExecutionGateway
{
    public function load(ExecutionContextData $context): mixed;

    public function assertReady(mixed $envelope, ExecutionContextData $context): void;

    public function lock(mixed $envelope, ExecutionContextData $context): mixed;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function childVoucherInstructions(mixed $envelope, ExecutionContextData $context): array;

    /**
     * @param  array<string, mixed>  $childInstruction
     * @return array<string, mixed>|null
     */
    public function claimFallbackInstructions(mixed $envelope, array $childInstruction, ExecutionContextData $context): ?array;
}
