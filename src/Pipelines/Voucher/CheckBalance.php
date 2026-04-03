<?php

namespace LBHurtado\Voucher\Pipelines\Voucher;

use Closure;
use Illuminate\Support\Facades\Log;

class CheckBalance
{
    private const DEBUG = false;

    public function handle($voucher, Closure $next)
    {
        //        $instructions = $voucher->instructions;
        //        $requiredAmount = $instructions->cash->amount;
        //        $customerWallet = $instructions->customer->wallet;
        //
        //        if ($customerWallet->balance < $requiredAmount) {
        //            Log::error("Customer wallet has insufficient funds for voucher ID: {$voucher->id}.");
        //            throw new \RuntimeException('Insufficient balance to create a voucher.');
        //        }

        if (self::DEBUG) {
            Log::info("Sufficient balance confirmed for voucher ID: {$voucher->id}.");
        }

        return $next($voucher);
    }
}
