<?php

namespace LBHurtado\Voucher\Services;

use Illuminate\Support\Str;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Contracts\GeneratesVouchers;
use LBHurtado\Voucher\Contracts\RedeemsVouchers;
use LBHurtado\Voucher\Contracts\SettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Exceptions\SettlementEnvelopeNotReadyException;

class SettlementEnvelopeExecutionDriver implements ExecutionDriverContract
{
    public function __construct(
        private readonly SettlementEnvelopeExecutionGateway $gateway,
        private readonly GeneratesVouchers $vouchers,
        private readonly RedeemsVouchers $redeemer,
    ) {}

    public function key(): string
    {
        return 'settlement_envelope';
    }

    public function execute(ExecutionContextData $context): ExecutionResultData
    {
        $executionId = (string) Str::uuid();
        $events = ['settlement_envelope.loaded'];
        $envelope = $this->gateway->load($context);
        $metadata = $this->metadataFor($context, $envelope);

        try {
            $this->gateway->assertReady($envelope, $context);
        } catch (SettlementEnvelopeNotReadyException $e) {
            return new ExecutionResultData(
                execution_id: $executionId,
                successful: false,
                status: 'failed',
                driver: $this->key(),
                events: $events,
                failure: 'settlement_envelope_not_ready',
                metadata: $metadata + [
                    'message' => $e->getMessage(),
                ],
            );
        }

        $events[] = 'settlement_envelope.ready';

        $lockedEnvelope = $this->gateway->lock($envelope, $context);
        $events[] = 'settlement_envelope.locked';

        $childInstructions = $this->gateway->childVoucherInstructions($lockedEnvelope, $context);
        $childVouchers = [];
        $fallbackClaimVouchers = [];

        foreach ($childInstructions as $childInstruction) {
            $childVouchers = array_merge($childVouchers, $this->generateVouchers($childInstruction));
        }

        if ($childVouchers !== []) {
            $events[] = 'settlement_envelope.children_generated';
        }

        if ($this->autoRedeemChildren($context)) {
            foreach ($childVouchers as $index => $childVoucherCode) {
                if ($this->redeemer->handle($context->contact, $childVoucherCode, [
                    'execution_id' => $executionId,
                    'parent_voucher_code' => $context->voucherCode,
                    'driver' => $this->key(),
                ])) {
                    continue;
                }

                if (! $this->fallbackToClaim($context)) {
                    return new ExecutionResultData(
                        execution_id: $executionId,
                        successful: false,
                        status: 'failed',
                        driver: $this->key(),
                        events: [...$events, 'settlement_envelope.child_auto_redeem_failed'],
                        failure: 'settlement_envelope_child_execution_failed',
                        metadata: $metadata + [
                            'child_vouchers' => $childVouchers,
                            'failed_child_voucher' => $childVoucherCode,
                        ],
                    );
                }

                $fallbackInstruction = $this->gateway->claimFallbackInstructions(
                    $lockedEnvelope,
                    $childInstructions[$index] ?? [],
                    $context,
                );

                if ($fallbackInstruction !== null) {
                    $fallbackClaimVouchers = array_merge($fallbackClaimVouchers, $this->generateVouchers($fallbackInstruction));
                    $events[] = 'settlement_envelope.child_fallback_generated';
                }
            }

            $events[] = 'settlement_envelope.children_auto_redeemed';
        }

        return new ExecutionResultData(
            execution_id: $executionId,
            successful: true,
            status: 'succeeded',
            driver: $this->key(),
            events: array_values(array_unique($events)),
            metadata: $metadata + [
                'locked' => true,
                'child_count' => count($childVouchers),
                'child_vouchers' => $childVouchers,
                'fallback_claim_vouchers' => $fallbackClaimVouchers,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $instructions
     * @return array<int, string>
     */
    private function generateVouchers(array $instructions): array
    {
        return $this->vouchers
            ->handle($instructions)
            ->map(fn (mixed $voucher): ?string => $this->voucherCode($voucher))
            ->filter()
            ->values()
            ->all();
    }

    private function voucherCode(mixed $voucher): ?string
    {
        if (is_object($voucher) && isset($voucher->code)) {
            return (string) $voucher->code;
        }

        if (is_array($voucher) && isset($voucher['code'])) {
            return (string) $voucher['code'];
        }

        return null;
    }

    private function autoRedeemChildren(ExecutionContextData $context): bool
    {
        return (bool) ($context->instruction?->metadata['auto_redeem_children'] ?? false);
    }

    private function fallbackToClaim(ExecutionContextData $context): bool
    {
        return (bool) ($context->instruction?->metadata['fallback_to_claim'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFor(ExecutionContextData $context, mixed $envelope): array
    {
        return [
            'envelope_reference' => $context->instruction?->metadata['envelope_reference'] ?? data_get($envelope, 'reference'),
            'authority_voucher_code' => $context->voucherCode,
            'driver' => $this->key(),
        ];
    }
}
