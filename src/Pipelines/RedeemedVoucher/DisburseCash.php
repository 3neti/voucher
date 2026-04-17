<?php

namespace LBHurtado\Voucher\Pipelines\RedeemedVoucher;

use LBHurtado\Voucher\Events\VoucherDisbursementFailed;
use LBHurtado\Voucher\Events\VoucherDisbursementSucceeded;
use LBHurtado\Voucher\Exceptions\InvalidSettlementRailException;
use LBHurtado\EmiCore\Contracts\BankRegistryContract;
use LBHurtado\Voucher\Events\DisburseInputPrepared;
use LBHurtado\Wallet\Events\DisbursementFailed;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\Contact\Classes\BankAccount;
use LBHurtado\Wallet\Actions\WithdrawCash;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use RuntimeException;
use Closure;

class DisburseCash
{
    private const DEBUG = false;

    public function __construct(protected PayoutProvider $gateway) {}

    /**
     * Attempts to disburse the Cash entity attached to the voucher.
     *
     * @param  mixed  $voucher
     * @return mixed
     */
    public function handle($voucher, Closure $next)
    {
        if (self::DEBUG) {
            Log::debug('[DisburseCash] Starting', ['voucher' => $voucher->code]);
        }

        // Compute slice amount for divisible vouchers (first slice on redemption)
        $sliceAmount = null;
        $sliceNumber = null;
        if ($voucher->isDivisible()) {
            if ($voucher->getSliceMode() === 'fixed') {
                $sliceAmount = $voucher->getSliceAmount();
                $sliceNumber = 1;
            } else {
                // Open mode: disburse user-chosen amount if provided, otherwise skip.
                $redeemer = $voucher->redeemers->first();
                $requestedAmount = $redeemer?->metadata['redemption']['inputs']['requested_amount'] ?? null;

                if ($requestedAmount !== null) {
                    $requestedAmount = (float) $requestedAmount;
                    $minWithdrawal = $voucher->getMinWithdrawal() ?? 0;
                    $faceAmount = $voucher->instructions->cash->amount ?? 0;

                    if ($requestedAmount < $minWithdrawal || $requestedAmount > $faceAmount) {
                        Log::warning('[DisburseCash] Open-mode requested_amount out of range', [
                            'voucher' => $voucher->code,
                            'requested' => $requestedAmount,
                            'min' => $minWithdrawal,
                            'max' => $faceAmount,
                        ]);

                        return $next($voucher);
                    }

                    $sliceAmount = $requestedAmount;
                    $sliceNumber = 1;

                    Log::info('[DisburseCash] Open-mode — disbursing user-chosen amount', [
                        'voucher' => $voucher->code,
                        'amount' => $sliceAmount,
                    ]);
                } else {
                    Log::info('[DisburseCash] Open-mode — no requested_amount, skipping auto-disburse', [
                        'voucher' => $voucher->code,
                    ]);

                    return $next($voucher);
                }
            }
        }

        $input = $this->buildPayoutRequest($voucher, $sliceAmount, $sliceNumber);

        event(new DisburseInputPrepared($voucher, $input));

        if (self::DEBUG) {
            Log::debug('[DisburseCash] Payload ready', ['input' => $input->toArray()]);
        }

        // CRITICAL: Validate EMI + PESONET combination
        $bankRegistry = $this->resolveBankRegistry();
        $rail = SettlementRail::from($input->settlement_rail);

        if ($rail === SettlementRail::PESONET && $bankRegistry->isEMI($input->bank_code)) {
            $bankName = $bankRegistry->getBankName($input->bank_code);

            Log::warning('[DisburseCash] EMI with PESONET detected - blocking disbursement', [
                'voucher' => $voucher->code,
                'bank_code' => $input->bank_code,
                'bank_name' => $bankName,
                'rail' => $rail->value,
                'amount' => $input->amount,
            ]);

            throw InvalidSettlementRailException::emiRequiresInstapay(
                $bankName,
                $input->bank_code,
                $rail->value
            );
        }

        // Attempt disbursement — failures should NOT roll back redemption.
        // Redemption is sacred: once user completes the flow, voucher stays redeemed.
        // Bank failures are recorded as 'pending' for later reconciliation.
        try {
            $response = $this->gateway->disburse($input);

            if ($response->status === PayoutStatus::FAILED) {
                throw new RuntimeException('Gateway returned failed status - disbursement failed');
            }
        } catch (\Throwable $e) {
            Log::warning('[DisburseCash] Disbursement failed — recording pending status', [
                'voucher' => $voucher->code,
                'error' => $e->getMessage(),
                'amount' => $input->amount,
                'bank' => $input->bank_code,
                'via' => $input->settlement_rail,
                'account_masked' => $this->maskAccountNumber($input->account_number),
            ]);

            $this->recordPendingDisbursement($voucher, $input, $bankRegistry, $e);

            event(new VoucherDisbursementFailed(
                voucher: $voucher,
                request: $input,
                exception: $e,
                sliceNumber: $sliceNumber,
            ));
            event(new DisbursementFailed($voucher, $e, $voucher->contact?->mobile));

            return $next($voucher);
        }

        // === SUCCESS PATH (gateway responded positively) ===

        // Store disbursement details on voucher in new generic format
        $bankName = $bankRegistry->getBankName($input->bank_code);
        $bankLogo = $bankRegistry->getBankLogo($input->bank_code);
        $isEmi = $bankRegistry->isEMI($input->bank_code);

        // Normalize status using PayoutStatus enum
        $providerName = $response->provider ?? 'unknown';
        $normalizedStatus = $response->status->value;

        // Get fee for the selected rail
        $rail = SettlementRail::from($input->settlement_rail);
        $feeAmount = $this->gateway->getRailFee($rail);
        $totalCost = ($input->amount * 100) + $feeAmount; // amount in pesos to centavos + fee
        $feeStrategy = $voucher->instructions?->cash?->fee_strategy ?? 'absorb';

        if (self::DEBUG) {
            Log::debug('[DisburseCash] Fee calculation', [
                'rail' => $rail->value,
                'fee_amount' => $feeAmount,
                'disbursement_amount' => $input->amount,
                'total_cost' => $totalCost,
                'fee_strategy' => $feeStrategy,
            ]);
        }

        // Withdraw funds from cash wallet (money has left the system)
        $cash = $voucher->cash;
        $withdrawAmountCentavos = $sliceAmount !== null ? (int) ($sliceAmount * 100) : null;
        $withdrawal = WithdrawCash::run(
            $cash,
            $response->transaction_id,
            'Disbursed to external bank account',
            [
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
                'flow' => 'redeem',
                'counterparty' => $bankName,
                'reference' => $input->account_number,
                'idempotency_key' => $response->uuid,
                'slice_number' => $sliceNumber,
            ],
            $withdrawAmountCentavos
        );

        $voucher->metadata = array_merge(
            $voucher->metadata ?? [],
            [
                'disbursement' => [
                    'gateway' => $providerName,
                    'transaction_id' => $response->transaction_id,
                    'status' => $normalizedStatus,
                    'amount' => $input->amount,
                    'currency' => 'PHP',
                    'settlement_rail' => $rail->value,
                    'fee_amount' => $feeAmount,
                    'total_cost' => $totalCost,
                    'fee_strategy' => $feeStrategy,
                    'recipient_identifier' => $input->account_number,
                    'disbursed_at' => now()->toIso8601String(),
                    'transaction_uuid' => $response->uuid,
                    'recipient_name' => $bankName,
                    'payment_method' => 'bank_transfer',
                    'cash_withdrawal_uuid' => $withdrawal->uuid,
                    'metadata' => [
                        'bank_code' => $input->bank_code,
                        'bank_name' => $bankName,
                        'bank_logo' => $bankLogo,
                        'rail' => $input->settlement_rail,
                        'is_emi' => $isEmi,
                        'operation_id' => $response->transaction_id,
                        'account' => $input->account_number,
                        'bank' => $input->bank_code,
                    ],
                ],
            ]
        );
        $voucher->save();

        Log::info('[DisburseCash] Success', [
            'voucher' => $voucher->code,
            'transactionId' => $response->transaction_id,
            'uuid' => $response->uuid,
            'status' => $response->status->value,
            'amount' => $input->amount,
            'bank' => $input->bank_code,
            'via' => $input->settlement_rail,
            'account_masked' => $this->maskAccountNumber($input->account_number),
        ]);

        event(new VoucherDisbursementSucceeded(
            voucher: $voucher,
            request: $input,
            result: $response,
            sliceNumber: $sliceNumber,
        ));

        return $next($voucher);
    }

    /**
     * Record a pending disbursement on the voucher when the gateway fails.
     * Preserves enough data for later reconciliation via disbursement:check/recover.
     */
    private function recordPendingDisbursement($voucher, PayoutRequestData $input, BankRegistryContract $bankRegistry, \Throwable $e): void
    {
        $bankName = $bankRegistry->getBankName($input->bank_code);

        $voucher->metadata = array_merge($voucher->metadata ?? [], [
            'disbursement' => [
                'gateway' => 'unknown',
                'transaction_id' => $input->reference,
                'status' => PayoutStatus::PENDING->value,
                'amount' => $input->amount,
                'currency' => 'PHP',
                'settlement_rail' => $input->settlement_rail,
                'recipient_identifier' => $input->account_number,
                'disbursed_at' => now()->toIso8601String(),
                'recipient_name' => $bankName,
                'payment_method' => 'bank_transfer',
                'error' => $e->getMessage(),
                'requires_reconciliation' => true,
                'metadata' => [
                    'bank_code' => $input->bank_code,
                    'bank_name' => $bankName,
                    'bank_logo' => $bankRegistry->getBankLogo($input->bank_code),
                    'rail' => $input->settlement_rail,
                    'is_emi' => $bankRegistry->isEMI($input->bank_code),
                ],
            ],
        ]);
        $voucher->save();
    }

    /**
     * Build a PayoutRequestData from a voucher for disbursement.
     */
    private function buildPayoutRequest($voucher, ?float $sliceAmount = null, ?int $sliceNumber = null): PayoutRequestData
    {
        $cash = $voucher->cash;
        if (! $cash) {
            throw new RuntimeException("Voucher {$voucher->code} has no cash entity");
        }

        $redeemer = $voucher->redeemer;
        if (! $redeemer) {
            throw new RuntimeException("Voucher {$voucher->code} has no redeemer");
        }

        $contact = $voucher->contact;
        if (! $contact) {
            throw new RuntimeException("Voucher {$voucher->code} has no Contact attached");
        }

        $rawBank = Arr::get($redeemer->metadata, 'redemption.bank_account');

        if (! is_string($rawBank) || trim($rawBank) === '') {
            $rawBank = (string) $contact->bank_account;
        }

        $bankAccount = BankAccount::fromBankAccountWithFallback(
            $rawBank,
            (string) $contact->bank_account
        );

        $baseReference = "{$voucher->code}-{$contact->mobile}";
        $reference = $sliceNumber !== null ? "{$baseReference}-S{$sliceNumber}" : $baseReference;
        $amount = $sliceAmount ?? $cash->amount->getAmount()->toFloat();
        $account = $bankAccount->getAccountNumber();
        $bank = $bankAccount->getBankCode();

        $settlementRailEnum = $voucher->instructions?->cash?->settlement_rail ?? null;

        if ($settlementRailEnum instanceof SettlementRail) {
            $via = $settlementRailEnum->value;
        } else {
            $via = $amount < 50000 ? 'INSTAPAY' : 'PESONET';
        }

        return PayoutRequestData::from([
            'reference' => $reference,
            'amount' => $amount,
            'account_number' => $account,
            'bank_code' => $bank,
            'settlement_rail' => $via,
            'external_id' => (string) $voucher->id,
            'external_code' => $voucher->code,
            'user_id' => $voucher->owner_id,
            'mobile' => $contact->mobile,
        ]);
    }

    private function resolveBankRegistry(): BankRegistryContract
    {
        return app(BankRegistryContract::class);
    }

    protected function maskAccountNumber(?string $accountNumber): ?string
    {
        if (! $accountNumber) {
            return null;
        }

        $length = strlen($accountNumber);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($accountNumber, -4);
    }
}
