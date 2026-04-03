<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Closure;

class MarkAsProcessed
{
    public function handle($vouchers, Closure $next)
    {
        $vouchers->each(function ($voucher) {
            $voucher->processed = true;
            $voucher->save();
        });

        return $next($vouchers);
    }
}
