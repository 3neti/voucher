<?php

namespace LBHurtado\Voucher\Handlers;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Models\Voucher;

class HandleUpdatedVoucher
{
    private const DEBUG = false;

    public function handle(Voucher $voucher): void
    {
        if (self::DEBUG) {
            Log::info('[HandleUpdatedVoucher] Starting pipeline for updated voucher.', [
                'voucher' => $voucher->code,
                'id' => $voucher->getKey(),
            ]);
        }

        $updated_pipeline = config('voucher-pipeline.updated');

        if (self::DEBUG) {
            Log::info('[HandleUpdatedVoucher] Pipeline arranged for updated voucher.', [
                'pipeline' => $updated_pipeline,
            ]);
        }

        try {
            app(Pipeline::class)
                ->send($voucher)
                ->through($updated_pipeline)
                ->then(function (Voucher $voucher) {
                    if (self::DEBUG) {
                        Log::info('[HandleUpdatedVoucher] Pipeline completed successfully.', [
                            'voucher' => $voucher->code,
                        ]);
                    }

                    return $voucher;
                });
        } catch (\Throwable $e) {
            Log::error('[HandleUpdatedVoucher] Pipeline failed.', [
                'voucher' => $voucher->code,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
