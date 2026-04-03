<?php

namespace LBHurtado\Voucher\Pipelines\Voucher;

use Bavix\Wallet\Interfaces\Customer;
use Closure;
use Illuminate\Support\Facades\Log;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\Voucher\Services\FeeCalculator;

class PersistCash
{
    private const DEBUG = false;

    public function __construct(
        protected FeeCalculator $feeCalculator
    ) {}

    public function handle($voucher, Closure $next)
    {
        if (self::DEBUG) {
            Log::debug('[PersistCash] Starting PersistCash for voucher', [
                'code' => $voucher->code,
                'instructions' => $voucher->instructions->toArray(),
            ]);
        }

        $user = $voucher->owner;

        if (self::DEBUG) {
            Log::debug('[RedeemVoucher] Voucher owner:', [
                'id' => $user?->getKey(),
                'class' => $user::class,
                'payload' => $user?->toArray(),
            ]);
        }

        if (! $user instanceof Customer) {
            throw new \Illuminate\Auth\AuthenticationException('You must implement customer to perform this action.');
        }

        $instructions = $voucher->instructions;
        $originalAmount = $instructions->cash->amount;
        $currency = $instructions->cash->currency;
        $secret = $instructions->cash->validation->secret;

        // Calculate adjusted amount based on fee strategy
        $feeCalculation = $this->feeCalculator->calculateAdjustedAmount($originalAmount, $instructions);
        $amount = $feeCalculation['adjusted_amount'];

        if (self::DEBUG) {
            Log::debug('[PersistCash] Creating Cash record', [
                'original_amount' => $originalAmount,
                'adjusted_amount' => $amount,
                'currency' => $currency,
                'secret' => $secret,
                'fee_strategy' => $feeCalculation['strategy'],
                'fee_amount' => $feeCalculation['fee_amount'],
                'rail' => $feeCalculation['rail'],
            ]);
        }

        $cash = Cash::create([
            'amount' => $amount,
            'currency' => $currency,
            'meta' => [
                'notes' => 'Cash entity with fee calculation',
                'original_amount' => $originalAmount,
                'fee_calculation' => $feeCalculation,
            ],
            ...($secret ? ['secret' => $secret] : []),
        ]);

        $user->pay($cash);

        if (self::DEBUG) {
            Log::info('[PersistCash] Cash record created', [
                'cash_id' => $cash->getKey(),
                'amount' => $cash->amount,
                'currency' => $cash->currency,
            ]);
        }

        $entities = ['cash' => $cash];
        $voucher->addEntities(...$entities);

        if (self::DEBUG) {
            Log::debug('[PersistCash] Attached cash entity to voucher', [
                'voucher_code' => $voucher->code,
                'attached' => array_keys($entities),
            ]);
        }

        return $next($voucher);
    }
}
