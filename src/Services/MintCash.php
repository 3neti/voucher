<?php

namespace LBHurtado\Voucher\Services;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

class MintCash
{
    use AsAction;

    private const DEBUG = false;

    public function handle(Voucher $voucher): Cash
    {
        try {
            $mint_pipeline = config('voucher-pipeline.mint-cash');

            if (self::DEBUG) {
                Log::debug('[MintCash] Starting minting process.', [
                    'voucher_id' => $voucher->id,
                    'voucher_code' => $voucher->code,
                    'pipeline' => $mint_pipeline,
                    'in_transaction' => DB::transactionLevel() > 0,
                ]);
            }

            $callback = fn () => app(Pipeline::class)
                ->send($voucher)
                ->through($mint_pipeline)
                ->thenReturn();

            if (DB::transactionLevel() > 0) {
                if (self::DEBUG) {
                    Log::debug('[MintCash] Already inside a transaction. Running pipeline inline.');
                }
                $callback();
            } else {
                if (self::DEBUG) {
                    Log::debug('[MintCash] Starting new transaction for pipeline.');
                }
                DB::transaction($callback);
            }

            $cash = $voucher->getEntities(Cash::class)->first();

            if (self::DEBUG) {
                Log::debug('[MintCash] Cash minted successfully.', [
                    'voucher_id' => $voucher->id,
                    'amount' => optional($cash)->amount?->getAmount()->toFloat(),
                    'currency' => optional($cash)->amount?->getCurrency()->getCurrencyCode(),
                    'cash_id' => optional($cash)->id,
                ]);
            }

            return $cash;
        } catch (\Throwable $th) {
            Log::error('[MintCash] Failed to mint cash.', [
                'voucher_id' => $voucher->id,
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            throw $th;
        }
    }
}
