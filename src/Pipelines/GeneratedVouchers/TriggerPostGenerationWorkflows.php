<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Closure;
use Illuminate\Support\Facades\Log;

class TriggerPostGenerationWorkflows
{
    private const DEBUG = false;

    public function handle($vouchers, Closure $next)
    {
        // @todo Trigger downstream tasks like syncing to external systems or scheduling dispatch
        if (self::DEBUG) {
            Log::info('Executing post-generation workflows for vouchers.');
        }

        $vouchers->each(function ($voucher) {
            // Placeholder for future integrations or job dispatching
        });

        return $next($vouchers);
    }
}
