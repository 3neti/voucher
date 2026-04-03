<?php

namespace LBHurtado\Voucher\Pipelines\RedeemedVoucher;

use Closure;
use Illuminate\Support\Facades\Log;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\Contact\Models\Contact;

class ValidateRedeemerAndCash
{
    /**
     * Ensure the voucher has both a Contact redeemer and a Cash entity.
     *
     * @param  mixed  $voucher
     * @return mixed
     */
    public function handle($voucher, Closure $next)
    {
        // 1) Check that there is a Contact
        if (! $voucher->contact instanceof Contact) {
            Log::warning('[ValidateRedeemerAndCash] No redeemer Contact on voucher', [
                'voucher' => $voucher->code,
            ]);

            // stop the pipeline
            return null;
        }

        // 2) Check that there is a Cash entity
        if (! $voucher->cash instanceof Cash) {
            Log::warning('[ValidateRedeemerAndCash] No Cash entity on voucher', [
                'voucher' => $voucher->code,
            ]);

            // stop the pipeline
            return null;
        }

        // both exist, continue…
        return $next($voucher);
    }
}
