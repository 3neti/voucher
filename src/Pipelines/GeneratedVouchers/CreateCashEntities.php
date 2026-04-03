<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Closure;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Services\MintCash;

class CreateCashEntities
{
    private const DEBUG = false;

    public function handle($vouchers, Closure $next)
    {
        if (self::DEBUG) {
            Log::debug('[CreateCashEntities] Starting to mint cash for vouchers.', [
                'total_vouchers' => $vouchers->count(),
            ]);
        }

        $vouchers->each(function ($voucher) {
            if (self::DEBUG) {
                Log::debug('[CreateCashEntities] Minting cash for voucher.', [
                    'voucher_id' => $voucher->id,
                    'voucher_code' => $voucher->code,
                ]);
            }
            MintCash::run($voucher);
            if (self::DEBUG) {
                Log::debug('[CreateCashEntities] Minted cash successfully.', [
                    'voucher_id' => $voucher->id,
                ]);
            }
        });
        if (self::DEBUG) {
            Log::debug('[CreateCashEntities] Finished processing all vouchers.');
        }

        return $next($vouchers);
    }
}
