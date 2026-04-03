<?php

namespace LBHurtado\Voucher\Listeners;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Events\VouchersGenerated;

class HandleGeneratedVouchers
{
    private const DEBUG = false;

    public function handle(VouchersGenerated $event): void
    {
        if (self::DEBUG) {
            Log::info('[HandleGeneratedVouchers] Event received', [
                'voucher_count' => $event->getVouchers()->count(),
            ]);
        }

        try {
            DB::transaction(function () use ($event) {
                $all = $event->getVouchers();
                if (self::DEBUG) {
                    Log::debug('[HandleGeneratedVouchers] Total vouchers in event', ['total' => $all->count()]);
                }

                // Process only unprocessed vouchers in the pipeline
                $unprocessed = $all->filter(fn ($voucher) => ! $voucher->processed);
                if (self::DEBUG) {
                    Log::debug('[HandleGeneratedVouchers] Unprocessed vouchers', ['count' => $unprocessed->count()]);
                }

                $post_generation_pipeline_array = config('voucher-pipeline.post-generation');

                app(Pipeline::class)
                    ->send($unprocessed)
                    ->through($post_generation_pipeline_array)
                    ->thenReturn();

                if (self::DEBUG) {
                    Log::info('[HandleGeneratedVouchers] Pipeline completed', ['processed' => $unprocessed->count()]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('[HandleGeneratedVouchers] Failed to process vouchers', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        if (self::DEBUG) {
            Log::info('[HandleGeneratedVouchers] Handler finished successfully');
        }
    }
}
