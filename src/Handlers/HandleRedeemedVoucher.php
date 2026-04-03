<?php

namespace LBHurtado\Voucher\Handlers;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Events\DisbursementRequested;
use LBHurtado\Voucher\Exceptions\InvalidSettlementRailException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Events\DisbursementFailed;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleRedeemedVoucher
{
    use AsAction;

    /**
     * Process a newly-redeemed voucher:
     *  1. Validate that it has both a cash entity and a contact
     *  2. Attempt to disburse funds
     *  3. Fire DisbursementRequested on success
     *  4. Fire DisbursementFailed (and rethrow) on any error
     *
     *
     * @throws \Throwable Allows the exception to bubble after firing DisbursementFailed
     */
    public function handle(Voucher $voucher): void
    {
        Log::info('[HandleRedeemedVoucher] Starting pipeline for redeemed voucher.', [
            'voucher' => $voucher->code,
            'id' => $voucher->getKey(),
        ]);

        $post_redemption_pipeline_array = config('voucher-pipeline.post-redemption');
        Log::info('[HandleRedeemedVoucher] Pipeline arranged for redeemed voucher.', [
            'pipeline' => $post_redemption_pipeline_array,
        ]);

        try {
            app(Pipeline::class)
                ->send($voucher)
                ->through($post_redemption_pipeline_array)
                ->then(function (Voucher $voucher) {
                    Log::info('[HandleRedeemedVoucher] Pipeline completed successfully; dispatching DisbursementRequested.', [
                        'voucher' => $voucher->code,
                    ]);

                    event(new DisbursementRequested($voucher));

                    return $voucher;
                });
        } catch (\Throwable $e) {
            // Handle known business exceptions gracefully (no stack trace)
            if ($e instanceof InvalidSettlementRailException) {
                Log::warning('[HandleRedeemedVoucher] Settlement rail validation failed.', [
                    'voucher' => $voucher->code,
                    'message' => $e->getMessage(),
                ]);
            } else {
                // Log unexpected errors with full trace for debugging
                Log::error('[HandleRedeemedVoucher] Pipeline failed; dispatching DisbursementFailed.', [
                    'voucher' => $voucher->code,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            event(new DisbursementFailed($voucher, $e));

            // rethrow so callers can handle it (or crash if unhandled)
            throw $e;
        }
    }
}
