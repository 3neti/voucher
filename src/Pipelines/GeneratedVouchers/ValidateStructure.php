<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Closure;

class ValidateStructure
{
    public function handle($vouchers, Closure $next)
    {
        foreach ($vouchers as $voucher) {
            $instructions = $voucher->instructions;

            if (! $instructions->cash || ! $instructions->inputs || ! $instructions->feedback) {
                throw new \Exception("Voucher metadata incomplete for voucher ID: {$voucher->id}");
            }

            // Additional field-specific checks
            if ($instructions->cash->amount === null || ! $instructions->cash->currency) {
                throw new \Exception("Cash instruction missing amount or currency for voucher ID: {$voucher->id}");
            }
        }

        return $next($vouchers);
    }
}
