<?php

namespace LBHurtado\Voucher\Services;

use FrittenKeeZ\Vouchers\Exceptions\VoucherNotFoundException;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use Throwable;

class DefaultExecutionDriver implements ExecutionDriverContract
{
    private const DEBUG = false;

    private const META_KEY = 'redemption';

    /**
     * Exception class names across voucher package versions that should
     * translate to a soft "false" instead of bubbling up.
     */
    private const SOFT_FAILURE_EXCEPTIONS = [
        'FrittenKeeZ\\Vouchers\\Exceptions\\VoucherNotFoundException',
        'FrittenKeeZ\\Vouchers\\Exceptions\\VoucherAlreadyRedeemedException',
        'FrittenKeeZ\\Vouchers\\Exceptions\\VoucherRedeemedException',
        'FrittenKeeZ\\Vouchers\\Exceptions\\VoucherExpiredException',
        'FrittenKeeZ\\Vouchers\\Exceptions\\VoucherUnstartedException',
    ];

    public function key(): string
    {
        return 'default';
    }

    public function execute(ExecutionContextData $context): ExecutionResultData
    {
        $metadata = $this->metadataFor($context);

        if (self::DEBUG) {
            Log::debug('[RedeemVoucher] Attempting redemption', [
                'voucher_code' => $context->voucherCode,
                'contact_id' => $context->contact->getKey(),
                'contact_mobile' => $context->contact->mobile,
                'meta' => $context->meta,
            ]);
        }

        try {
            $successful = Vouchers::redeem(
                $context->voucherCode,
                $context->contact,
                [self::META_KEY => $context->meta]
            );

            Log::info('[RedeemVoucher] Redemption succeeded', [
                'voucher_code' => $context->voucherCode,
                'contact_id' => $context->contact->getKey(),
            ]);

            if ($successful) {
                return ExecutionResultData::succeeded($this->key(), $metadata);
            }

            return ExecutionResultData::failed(
                driver: $this->key(),
                failure: 'compatibility_redemption_rejected',
                metadata: $metadata,
            );
        } catch (VoucherNotFoundException $e) {
            Log::warning('[RedeemVoucher] Voucher not found', [
                'voucher_code' => $context->voucherCode,
                'contact_id' => $context->contact->getKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return ExecutionResultData::failed(
                driver: $this->key(),
                failure: 'compatibility_redemption_rejected',
                metadata: $metadata,
            );
        } catch (Throwable $e) {
            if ($this->isSoftFailureException($e)) {
                Log::warning('[RedeemVoucher] Voucher redemption rejected', [
                    'voucher_code' => $context->voucherCode,
                    'contact_id' => $context->contact->getKey(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                return ExecutionResultData::failed(
                    driver: $this->key(),
                    failure: 'compatibility_redemption_rejected',
                    metadata: $metadata,
                );
            }

            throw $e;
        }
    }

    private function isSoftFailureException(Throwable $e): bool
    {
        foreach (self::SOFT_FAILURE_EXCEPTIONS as $exceptionClass) {
            if (is_a($e, $exceptionClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFor(ExecutionContextData $context): array
    {
        return [
            'voucher_code' => $context->voucherCode,
            'voucher_id' => $context->voucher?->getKey(),
            'contact_id' => $context->contact->getKey(),
            'driver' => $this->key(),
            'correlation' => $context->correlation,
        ];
    }
}

