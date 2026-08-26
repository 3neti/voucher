<?php

namespace LBHurtado\Voucher\Services;

use Illuminate\Support\Str;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Contracts\PayableCollectionExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Exceptions\PayableCollectionRejectedException;

class PayableCollectionExecutionDriver implements ExecutionDriverContract
{
    public function __construct(
        private readonly PayableCollectionExecutionGateway $gateway,
    ) {}

    public function key(): string
    {
        return 'payable_collection';
    }

    public function execute(ExecutionContextData $context): ExecutionResultData
    {
        $executionId = $this->executionId($context);
        $operation = trim((string) ($context->meta['operation'] ?? ''));

        if ($operation !== 'collect') {
            return new ExecutionResultData(
                execution_id: $executionId,
                successful: false,
                status: 'failed',
                driver: $this->key(),
                events: ['payable_collection.execution_requested'],
                failure: 'payable_collection_operation_unsupported',
                metadata: $this->baseMetadata($context) + [
                    'operation' => $operation !== '' ? $operation : null,
                    'message' => 'Payable collection requires the collect operation.',
                ],
            );
        }

        return $this->collect($context, $executionId);
    }

    private function collect(ExecutionContextData $context, string $executionId): ExecutionResultData
    {
        $events = ['payable_collection.collection_requested'];

        try {
            $amountMinor = $this->amountMinor($context);
            $providerTransactionId = $this->providerTransactionId($context);
            $authorization = $this->gateway->authorize($context, $executionId);
            $events[] = 'payable_collection.authorized';
            $state = $this->gateway->credit(
                $context,
                $amountMinor,
                $providerTransactionId,
                $executionId,
            );
        } catch (PayableCollectionRejectedException $exception) {
            return new ExecutionResultData(
                execution_id: $executionId,
                successful: false,
                status: 'failed',
                driver: $this->key(),
                events: $events,
                failure: 'payable_collection_rejected',
                metadata: $this->baseMetadata($context) + [
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return new ExecutionResultData(
            execution_id: $executionId,
            successful: true,
            status: 'succeeded',
            driver: $this->key(),
            events: [...$events, 'payable_collection.credited'],
            metadata: $this->baseMetadata($context) + $authorization + $state + [
                'collected_amount_minor' => $amountMinor,
            ],
        );
    }

    private function executionId(ExecutionContextData $context): string
    {
        $executionId = trim((string) ($context->correlation['execution_id'] ?? ''));

        if ($executionId === '') {
            return (string) Str::uuid();
        }

        if (mb_strlen($executionId) > 160) {
            throw new PayableCollectionRejectedException(
                'Payable collection execution identity may not exceed 160 characters.',
            );
        }

        return $executionId;
    }

    private function amountMinor(ExecutionContextData $context): int
    {
        $amountMinor = (int) ($context->meta['amount_minor'] ?? 0);

        if ($amountMinor <= 0) {
            throw new PayableCollectionRejectedException(
                'Payable collection amount must be positive.',
            );
        }

        return $amountMinor;
    }

    private function providerTransactionId(ExecutionContextData $context): string
    {
        $providerTransactionId = trim((string) ($context->meta['provider_transaction_id'] ?? ''));

        if ($providerTransactionId === '') {
            throw new PayableCollectionRejectedException(
                'Payable collection requires a provider transaction identity.',
            );
        }

        return $providerTransactionId;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseMetadata(ExecutionContextData $context): array
    {
        return [
            'authority_voucher_code' => $context->voucherCode,
            'driver' => $this->key(),
        ];
    }
}
