<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Closure;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckFundsAvailability
{
    public function handle($vouchers, Closure $next)
    {
        // Calculate the total amount and currency from all vouchers
        $totalAmount = 0;
        $currency = null;

        foreach ($vouchers as $voucher) {
            $instructions = $voucher->instructions;
            $amount = $instructions->cash->amount ?? null;
            $voucherCurrency = $instructions->cash->currency ?? null;

            // Ensure amount and currency are present
            if (! $amount || ! $voucherCurrency) {
                throw new \Exception("Missing amount or currency in voucher metadata for voucher ID: {$voucher->id}.");
            }

            // Validate that all vouchers use the same currency
            if ($currency && $currency !== $voucherCurrency) {
                throw new \Exception("Currency mismatch detected for voucher ID: {$voucher->id}. Expected: {$currency}, found: {$voucherCurrency}.");
            }

            $currency = $voucherCurrency;
            $totalAmount += $amount;
        }

        try {
            // API endpoint and credentials
            $apiEndpoint = config('services.funds_api.endpoint', 'https://x-change.disburse.cash');
            $apiKey = config('services.funds_api.api_key', 'AA537234-1234-5678-9012-345678901234');

            // Make the API call to check fund availability
            $response = Http::withHeaders([
                'Authorization' => "Bearer $apiKey",
            ])->post($apiEndpoint, [
                'amount' => $totalAmount, // Total amount to be generated
                'currency' => $currency, // Currency used for all vouchers
            ]);

            // Check if the response indicates funds are available
            if ($response->failed()) {
                throw new \Exception("Funds API request failed with status code: {$response->status()}");
            }

            $responseData = $response->json();

            if (! $responseData['available']) {
                throw new \Exception("Funds unavailable for the total amount: $totalAmount $currency.");
            }
        } catch (\Exception $e) {
            // Log the error and stop further processing
            Log::error("Failed to verify funds availability. Error: {$e->getMessage()}");
            throw $e; // Stop pipeline execution
        }

        // Proceed to the next step if funds are available
        return $next($vouchers);
    }
}
