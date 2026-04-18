<?php

namespace LBHurtado\Voucher\Pipelines\RedeemedVoucher;

use ArrayObject;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Support\RedemptionLocationValidator;

class ValidateRedemptionContract
{
    public function __construct(
        protected ?RedemptionLocationValidator $locationValidator = null,
    ) {
        $this->locationValidator ??= new RedemptionLocationValidator;
    }

    public function handle(Voucher $voucher, Closure $next): Voucher
    {
        $validation = $voucher->instructions?->validation;

        if (! $validation) {
            return $next($voucher);
        }

        $violations = [];

        // IMPORTANT: redemption metadata lives on the redeemer record, not the Contact model.
        $redeemerRecord = $voucher->redeemers()->latest('id')->first();
        $metadata = $this->normalizeMetadata($redeemerRecord?->metadata);

        if ($validation->signature?->required) {
            $signature = Arr::get($metadata, 'redemption.signature');

            if (! is_string($signature) || trim($signature) === '') {
                $violations['signature'] = 'missing';
            }
        }

        if ($validation->selfie?->required) {
            $selfie = Arr::get($metadata, 'redemption.selfie');

            if (! is_string($selfie) || trim($selfie) === '') {
                $violations['selfie'] = 'missing';
            }
        }

        if ($validation->location?->required) {
            $currentLat = Arr::get($metadata, 'redemption.location.lat');
            $currentLng = Arr::get($metadata, 'redemption.location.lng');

            if ($currentLat === null || $currentLng === null) {
                $violations['location'] = 'missing';
            } elseif (
                $validation->location->target_lat !== null
                && $validation->location->target_lng !== null
                && $validation->location->radius_meters !== null
            ) {
                $withinRadius = $this->locationValidator->isWithinRadius(
                    (float) $currentLat,
                    (float) $currentLng,
                    (float) $validation->location->target_lat,
                    (float) $validation->location->target_lng,
                    (int) $validation->location->radius_meters,
                );

                if (! $withinRadius) {
                    $violations['location'] = 'outside_radius';
                }
            }
        }

        if ($violations === []) {
            return $next($voucher);
        }

        $this->persistValidationFailures($voucher, $violations);

        $shouldBlock = $this->shouldBlock($validation, $violations);

        if (! $shouldBlock) {
            Log::warning('[ValidateRedemptionContract] Validation warnings only.', [
                'voucher_code' => $voucher->code,
                'violations' => $violations,
            ]);

            return $next($voucher);
        }

        throw new VoucherRedemptionContractViolationException($violations);
    }

    protected function persistValidationFailures(Voucher $voucher, array $violations): void
    {
        $metadata = $voucher->metadata ?? [];

        $metadata['redemption_validation'] = [
            'passed' => false,
            'violations' => $violations,
            'checked_at' => now()->toIso8601String(),
        ];

        $voucher->metadata = $metadata;
        $voucher->save();
    }

    protected function shouldBlock(object $validation, array $violations): bool
    {
        foreach ($violations as $field => $reason) {
            $mode = match ($field) {
                'signature' => $validation->signature?->on_failure ?? 'block',
                'selfie' => $validation->selfie?->on_failure ?? 'block',
                'location' => $validation->location?->on_failure ?? 'block',
                default => 'block',
            };

            if ($mode === 'block') {
                return true;
            }
        }

        return false;
    }

    protected function normalizeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if ($metadata instanceof ArrayObject) {
            return $metadata->getArrayCopy();
        }

        if ($metadata instanceof Arrayable) {
            return $metadata->toArray();
        }

        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            return $metadata->toArray();
        }

        return [];
    }
}