<?php

namespace LBHurtado\Voucher\Services;

use Illuminate\Support\Str;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Contracts\StoredValueExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRequiresOtpException;

class StoredValueExecutionDriver implements ExecutionDriverContract
{
    public function __construct(
        private readonly StoredValueExecutionGateway $gateway,
    ) {}

    public function key(): string
    {
        return 'stored_value';
    }

    public function execute(ExecutionContextData $context): ExecutionResultData
    {
        $executionId = (string) Str::uuid();

        return match ($this->operation($context)) {
            'spend' => $this->spend($context, $executionId),
            'replenish' => $this->replenish($context, $executionId),
            default => $this->activate($context, $executionId),
        };
    }

    private function activate(ExecutionContextData $context, string $executionId): ExecutionResultData
    {
        $state = $this->gateway->activate($context, $executionId);

        return new ExecutionResultData(
            execution_id: $executionId,
            successful: true,
            status: 'succeeded',
            driver: $this->key(),
            events: ['stored_value.ownership_claimed', 'stored_value.activated'],
            metadata: $this->metadataFor($context, $state) + [
                'disbursement_skipped' => true,
            ],
        );
    }

    private function spend(ExecutionContextData $context, string $executionId): ExecutionResultData
    {
        $amount = $this->amount($context);
        $events = ['stored_value.spend_requested'];

        try {
            $this->assertOtpSatisfied($context, $amount);
            $state = $this->gateway->spend($context, $amount, $executionId);
        } catch (StoredValueSpendRequiresOtpException $e) {
            return $this->failedSpend(
                context: $context,
                executionId: $executionId,
                failure: 'stored_value_otp_required',
                events: $events,
                metadata: [
                    'message' => $e->getMessage(),
                    'requested_amount' => $amount,
                    'otp_required_above' => $this->otpRequiredAbove($context),
                ],
            );
        } catch (StoredValueSpendRejectedException $e) {
            return $this->failedSpend(
                context: $context,
                executionId: $executionId,
                failure: 'stored_value_spend_rejected',
                events: $events,
                metadata: [
                    'message' => $e->getMessage(),
                    'requested_amount' => $amount,
                ],
            );
        }

        return new ExecutionResultData(
            execution_id: $executionId,
            successful: true,
            status: 'succeeded',
            driver: $this->key(),
            events: [...$events, 'stored_value.spent'],
            metadata: $this->metadataFor($context, $state) + [
                'spent_amount' => $amount,
                'merchant_reference' => $context->meta['merchant_reference'] ?? null,
            ],
        );
    }

    private function replenish(ExecutionContextData $context, string $executionId): ExecutionResultData
    {
        $amount = $this->amount($context);

        if (! $this->replenishable($context)) {
            return new ExecutionResultData(
                execution_id: $executionId,
                successful: false,
                status: 'failed',
                driver: $this->key(),
                events: ['stored_value.replenishment_requested'],
                failure: 'stored_value_replenishment_rejected',
                metadata: $this->baseMetadata($context) + [
                    'requested_amount' => $amount,
                    'message' => 'Stored value voucher is not replenishable.',
                ],
            );
        }

        $remainingBalance = $this->gateway->balance($context);
        $maxBalance = $this->maxBalance($context);

        if ($maxBalance > 0 && $remainingBalance + $amount > $maxBalance) {
            return new ExecutionResultData(
                execution_id: $executionId,
                successful: false,
                status: 'failed',
                driver: $this->key(),
                events: ['stored_value.replenishment_requested'],
                failure: 'stored_value_replenishment_rejected',
                metadata: $this->baseMetadata($context) + [
                    'requested_amount' => $amount,
                    'remaining_balance' => $remainingBalance,
                    'max_balance' => $maxBalance,
                    'message' => 'Stored value replenishment exceeds the configured maximum balance.',
                ],
            );
        }

        try {
            $state = $this->gateway->replenish($context, $amount, $executionId);
        } catch (StoredValueSpendRejectedException $e) {
            return new ExecutionResultData(
                execution_id: $executionId,
                successful: false,
                status: 'failed',
                driver: $this->key(),
                events: ['stored_value.replenishment_requested'],
                failure: 'stored_value_replenishment_rejected',
                metadata: $this->baseMetadata($context) + [
                    'requested_amount' => $amount,
                    'remaining_balance' => $this->gateway->balance($context),
                    'message' => $e->getMessage(),
                ],
            );
        }

        return new ExecutionResultData(
            execution_id: $executionId,
            successful: true,
            status: 'succeeded',
            driver: $this->key(),
            events: ['stored_value.replenishment_requested', 'stored_value.replenished'],
            metadata: $this->metadataFor($context, $state) + [
                'replenished_amount' => $amount,
            ],
        );
    }

    /**
     * @param  array<int, string>  $events
     * @param  array<string, mixed>  $metadata
     */
    private function failedSpend(
        ExecutionContextData $context,
        string $executionId,
        string $failure,
        array $events,
        array $metadata,
    ): ExecutionResultData {
        return new ExecutionResultData(
            execution_id: $executionId,
            successful: false,
            status: 'failed',
            driver: $this->key(),
            events: $events,
            failure: $failure,
            metadata: $this->baseMetadata($context) + [
                'remaining_balance' => $this->gateway->balance($context),
            ] + $metadata,
        );
    }

    private function assertOtpSatisfied(ExecutionContextData $context, int $amount): void
    {
        $threshold = $this->otpRequiredAbove($context);

        if ($threshold > 0 && $amount > $threshold && empty($context->meta['otp_verified'])) {
            throw new StoredValueSpendRequiresOtpException('OTP is required for this stored value spend.');
        }
    }

    private function operation(ExecutionContextData $context): string
    {
        return (string) ($context->meta['operation'] ?? 'activate');
    }

    private function amount(ExecutionContextData $context): int
    {
        return max(0, (int) ($context->meta['amount'] ?? 0));
    }

    private function replenishable(ExecutionContextData $context): bool
    {
        return (bool) ($context->instruction?->metadata['replenishable'] ?? false);
    }

    private function otpRequiredAbove(ExecutionContextData $context): int
    {
        return (int) ($context->instruction?->metadata['otp_required_above'] ?? 0);
    }

    private function maxBalance(ExecutionContextData $context): int
    {
        return (int) ($context->instruction?->metadata['max_balance'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function metadataFor(ExecutionContextData $context, array $state): array
    {
        return $this->baseMetadata($context) + $state;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseMetadata(ExecutionContextData $context): array
    {
        return [
            'stored_value_reference' => $context->instruction?->metadata['stored_value_reference'] ?? $context->voucherCode,
            'authority_voucher_code' => $context->voucherCode,
            'driver' => $this->key(),
        ];
    }
}
