<?php

namespace LBHurtado\Voucher\Actions;

use FrittenKeeZ\Vouchers\Exceptions\VoucherNotFoundException;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Models\Contact;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedeemVoucher
{
    use AsAction;

    private const DEBUG = false;

    public const META_KEY = 'redemption';

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

    /**
     * Attempt to redeem a voucher for a given contact.
     *
     * @param  Contact  $contact
     * @param  string  $voucher_code
     * @param  array  $meta
     * @return bool
     *
     * @throws Throwable
     */
    public function handle(Contact $contact, string $voucher_code, array $meta = []): bool
    {
        if (self::DEBUG) {
            Log::debug('[RedeemVoucher] Attempting redemption', [
                'voucher_code' => $voucher_code,
                'contact_id' => $contact->getKey(),
                'contact_mobile' => $contact->mobile,
                'meta' => $meta,
            ]);
        }

        try {
            $success = Vouchers::redeem(
                $voucher_code,
                $contact,
                [self::META_KEY => $meta]
            );

            Log::info('[RedeemVoucher] Redemption succeeded', [
                'voucher_code' => $voucher_code,
                'contact_id' => $contact->getKey(),
            ]);

            return $success;
        } catch (VoucherNotFoundException $e) {
            Log::warning('[RedeemVoucher] Voucher not found', [
                'voucher_code' => $voucher_code,
                'contact_id' => $contact->getKey(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return false;
        } catch (Throwable $e) {
            if ($this->isSoftFailureException($e)) {
                Log::warning('[RedeemVoucher] Voucher redemption rejected', [
                    'voucher_code' => $voucher_code,
                    'contact_id' => $contact->getKey(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                return false;
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
}