<?php

namespace LBHurtado\Voucher\Services;

use Illuminate\Support\Facades\Log;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

class FeeCalculator
{
    private const DEBUG = false;

    public function __construct(
        protected PayoutProvider $gateway
    ) {}

    /**
     * Calculate the adjusted amount based on fee strategy.
     *
     * @param  float  $originalAmount  Original voucher amount in pesos
     * @param  VoucherInstructionsData  $instructions  Voucher instructions
     * @return array{adjusted_amount: float, fee_amount: int, total_cost: int, strategy: string, rail: string}
     */
    public function calculateAdjustedAmount(
        float $originalAmount,
        VoucherInstructionsData $instructions
    ): array {
        $feeStrategy = $instructions->cash->fee_strategy ?? 'absorb';
        $settlementRail = $instructions->cash->settlement_rail;

        // Determine rail for fee calculation
        if ($settlementRail === null) {
            // Auto-select based on amount
            $rail = $originalAmount < 50000 ? SettlementRail::INSTAPAY : SettlementRail::PESONET;
        } else {
            $rail = $settlementRail;
        }

        // Get fee in centavos
        $feeAmount = $this->gateway->getRailFee($rail);
        $feeInPesos = $feeAmount / 100;

        // Calculate adjusted amount based on strategy
        $adjustedAmount = match ($feeStrategy) {
            'include' => $originalAmount - $feeInPesos, // Deduct fee from voucher
            'add' => $originalAmount, // Keep original, fee added separately (recipient pays)
            'absorb' => $originalAmount, // Keep original, issuer pays fee
            default => $originalAmount,
        };

        // Ensure adjusted amount is never negative
        if ($adjustedAmount < 0) {
            Log::warning('[FeeCalculator] Adjusted amount would be negative, using 0', [
                'original_amount' => $originalAmount,
                'fee_amount' => $feeInPesos,
                'strategy' => $feeStrategy,
            ]);
            $adjustedAmount = 0;
        }

        // Calculate total cost (amount + fee if applicable)
        $totalCost = match ($feeStrategy) {
            'add' => ($originalAmount + $feeInPesos) * 100, // Convert to centavos
            default => $originalAmount * 100, // Issuer pays or already included
        };

        if (self::DEBUG) {
            Log::debug('[FeeCalculator] Fee calculation complete', [
                'original_amount' => $originalAmount,
                'adjusted_amount' => $adjustedAmount,
                'fee_amount' => $feeAmount,
                'fee_in_pesos' => $feeInPesos,
                'total_cost' => $totalCost,
                'strategy' => $feeStrategy,
                'rail' => $rail->value,
            ]);
        }

        return [
            'adjusted_amount' => $adjustedAmount,
            'fee_amount' => $feeAmount,
            'total_cost' => (int) $totalCost,
            'strategy' => $feeStrategy,
            'rail' => $rail->value,
        ];
    }
}
