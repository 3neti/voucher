<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Closure;

class NormalizeMetadata
{
    public function handle($vouchers, Closure $next)
    {
        $vouchers->each(function ($voucher) {
            $instructions = $voucher->metadata['instructions'] ?? [];

            if (isset($instructions['cash']['amount'])) {
                $instructions['cash']['amount'] =
                    round(num: $instructions['cash']['amount'], precision: 2, mode: PHP_ROUND_HALF_DOWN);
            }

            if (isset($instructions['cash']['currency'])) {
                $instructions['cash']['currency'] = strtoupper(trim($instructions['cash']['currency']));
            }

            $voucher->metadata = array_merge($voucher->metadata, ['instructions' => $instructions]);
            $voucher->save();
        });

        return $next($vouchers);
    }
}
