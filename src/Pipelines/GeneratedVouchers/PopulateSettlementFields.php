<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Enums\VoucherType;

/**
 * Pipeline Stage: Populate Settlement Fields
 *
 * Reads voucher_type, target_amount, and rules from instructions
 * and populates the corresponding database columns for settlement vouchers.
 */
class PopulateSettlementFields
{
    private const DEBUG = true;

    public function handle(Collection $vouchers, Closure $next): mixed
    {
        if (self::DEBUG) {
            Log::info('[PopulateSettlementFields] Processing vouchers', [
                'count' => $vouchers->count(),
            ]);
        }

        foreach ($vouchers as $voucher) {
            $instructions = $voucher->instructions;

            // Check if voucher has settlement fields in instructions
            if (! isset($instructions->voucher_type) || $instructions->voucher_type === null) {
                // Default to REDEEMABLE if not specified
                $voucher->forceFill([
                    'voucher_type' => VoucherType::REDEEMABLE,
                    'state' => VoucherState::ACTIVE,
                ])->save();

                if (self::DEBUG) {
                    Log::debug('[PopulateSettlementFields] Defaulted to REDEEMABLE', [
                        'code' => $voucher->code,
                    ]);
                }

                continue;
            }

            // Parse voucher type from instructions (might already be an enum)
            $voucherType = $instructions->voucher_type instanceof VoucherType
                ? $instructions->voucher_type
                : VoucherType::tryFrom($instructions->voucher_type);

            if (! $voucherType) {
                Log::warning('[PopulateSettlementFields] Invalid voucher_type in instructions', [
                    'code' => $voucher->code,
                    'voucher_type' => is_object($instructions->voucher_type) ? get_class($instructions->voucher_type) : $instructions->voucher_type,
                ]);

                continue;
            }

            // Determine initial state
            $state = VoucherState::ACTIVE;

            // Build update data
            $updateData = [
                'voucher_type' => $voucherType,
                'state' => $state,
            ];

            // Add target_amount for payable/settlement types
            if (in_array($voucherType, [VoucherType::PAYABLE, VoucherType::SETTLEMENT])) {
                if (isset($instructions->target_amount) && $instructions->target_amount > 0) {
                    $updateData['target_amount'] = $instructions->target_amount;
                } else {
                    Log::warning('[PopulateSettlementFields] Missing target_amount for payable/settlement voucher', [
                        'code' => $voucher->code,
                        'type' => $voucherType->value,
                    ]);
                }
            }

            // Add rules if present
            if (isset($instructions->rules) && ! empty($instructions->rules)) {
                $updateData['rules'] = $instructions->rules;
            }

            // Update voucher (use forceFill to bypass mass assignment protection)
            $voucher->forceFill($updateData)->save();

            if (self::DEBUG) {
                Log::debug('[PopulateSettlementFields] Updated voucher', [
                    'code' => $voucher->code,
                    'type' => $voucherType->value,
                    'state' => $state->value,
                    'target_amount' => $updateData['target_amount'] ?? null,
                ]);
            }
        }

        if (self::DEBUG) {
            Log::info('[PopulateSettlementFields] Completed processing');
        }

        return $next($vouchers);
    }
}
