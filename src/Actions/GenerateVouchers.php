<?php

namespace LBHurtado\Voucher\Actions;

use Carbon\CarbonInterval;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Data\VoucherMetadataData;
use LBHurtado\Voucher\Events\VouchersGenerated;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateVouchers
{
    use AsAction;

    private const DEBUG = false;

    // TODO: explicitly add owner in the parameter
    public function handle(VoucherInstructionsData|array $instructions): Collection
    {
        if (is_array($instructions)) {
            $instructions = VoucherInstructionsData::createFromAttribs($instructions);
        }

        if (self::DEBUG) {
            Log::debug('[GenerateVouchers] Received count='.$instructions->count);
            Log::debug('[GenerateVouchers] Raw DTO:', $instructions->toArray());
        }

        // Extract parameters from instructions
        $count = $instructions->count ?? 1; // Use 'count' from instructions or default to 1
        $prefix = $instructions->prefix ?? config('x-change.generate.prefix');
        $mask = $instructions->mask ?? config('x-change.generate.mask');

        // Validate or fallback the mask
        $validator = Validator::make(['mask' => $mask], [
            'mask' => ['required', 'string', 'min:4', 'regex:/\*/'],
        ]);

        if ($validator->fails()) {
            if (self::DEBUG) {
                Log::warning('[GenerateVouchers] Invalid mask provided. Using default mask.');
            }
            $mask = '****';
        }

//        $ttl = $instructions->ttl instanceof CarbonInterval
//            ? $instructions->ttl
//            : CarbonInterval::hours(12); // Default TTL to 12 hours if missing

        if (self::DEBUG) {
            Log::debug('[GenerateVouchers] About to create', compact('count', 'prefix', 'mask', 'ttl'));
        }

        $owner = auth()->user();
        if (is_null($owner)) {
            throw new \Exception('No authenticated user found. Please ensure a user is logged in.');
        }

        // Populate metadata if not already provided
        if (is_null($instructions->metadata)) {
            $instructions->metadata = $this->createMetadata($owner);
        }

        $voucherBuilder = Vouchers::withPrefix($prefix)
            ->withMask($mask)
            ->withMetadata(['instructions' => $instructions->toCleanArray()]) // This is most important! Pass instructions as metadata.
            ->withOwner($owner);

        // ✅ START TIME
        if ($instructions->starts_at) {
            $voucherBuilder->withStartTime($instructions->starts_at);
        }

// ✅ EXPIRE TIME (priority 1)
        if ($instructions->expires_at) {
            $voucherBuilder->withExpireTime($instructions->expires_at);
        } elseif ($instructions->ttl) {
            $voucherBuilder->withExpireTimeIn($instructions->ttl);
        } else
            $voucherBuilder->withExpireTimeIn(CarbonInterval::hours(12)); //TODO: make this configurable

        $vouchers = $voucherBuilder->create($count);
        if (self::DEBUG) {
            Log::debug('[GenerateVouchers] Raw facade response', ['raw' => $vouchers]);
        }

        /** @var \Illuminate\Support\Collection<int, \FrittenKeeZ\Vouchers\Models\Voucher> $collection */
        $collection = collect(is_array($vouchers) ? $vouchers : [$vouchers]);

        if (self::DEBUG) {
            Log::debug('[GenerateVouchers] Normalized voucher list', [
                'count' => $collection->count(),
                'codes' => $collection->pluck('code')->all(),
            ]);
        }

        // Dispatch the event with the generated vouchers
        VouchersGenerated::dispatch($collection);

        return $collection;
    }

    /**
     * Create metadata for voucher instructions.
     */
    private function createMetadata($owner): VoucherMetadataData
    {
        // Get redemption URLs
        $redemptionUrls = [
            'web' => route('redeem.start'),
        ];

        // Add API endpoint if route exists
        if (Route::has('api.redemption.validate')) {
            $redemptionUrls['api'] = route('api.redemption.validate');
        }

        // Add widget URL if configured
        if ($widgetUrl = config('voucher.redemption.widget_url')) {
            $redemptionUrls['widget'] = $widgetUrl;
        }

        // Determine primary URL (prefer web)
        $primaryUrl = $redemptionUrls['web'] ?? null;

        // Collect active licenses (non-null values)
        $licenses = array_filter(config('voucher.metadata.licenses', []));

        return VoucherMetadataData::from([
            'version' => (string) config('voucher.metadata.version'),
            'system_name' => config('voucher.metadata.system_name'),
            'copyright' => config('voucher.metadata.copyright'),
            'licenses' => $licenses,
            'issuer_id' => (string) $owner->id,
            'issuer_name' => $owner->name ?? $owner->email,
            'issuer_email' => $owner->email,
            'redemption_urls' => $redemptionUrls,
            'primary_url' => $primaryUrl,
            'created_at' => now()->toIso8601String(),
            'issued_at' => now()->toIso8601String(),

            // Optional fields (signature support)
            'public_key' => config('voucher.security.enable_signatures')
                ? config('voucher.security.public_key')
                : null,
        ]);
    }
}
