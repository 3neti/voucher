<?php

namespace LBHurtado\Voucher\Actions;

use FrittenKeeZ\Vouchers\Exceptions\VoucherAlreadyRedeemedException;
use FrittenKeeZ\Vouchers\Exceptions\VoucherNotFoundException;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Support\Facades\Log;
use LBHurtado\Contact\Models\Contact;
use Lorisleiva\Actions\Concerns\AsAction;

class RedeemVoucher
{
    use AsAction;

    private const DEBUG = false;

    const META_KEY = 'redemption';

    /**
     * Attempt to redeem a voucher for a given contact.
     *
     * @param  Contact  $contact  The notifiable contact who redeems the voucher.
     * @param  string  $voucher_code  The code of the voucher to redeem.
     * @param  array  $meta  Optional metadata about the redemption.
     * @return bool True if redemption succeeded, false otherwise.
     */
    public function handle(Contact $contact, string $voucher_code, array $meta = []): bool
    {
        // 1) Log what we're about to attempt
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
            ]);

            return false;
        } catch (VoucherAlreadyRedeemedException $e) {
            Log::warning('[RedeemVoucher] Voucher already redeemed', [
                'voucher_code' => $voucher_code,
                'contact_id' => $contact->getKey(),
            ]);

            return false;
        }
    }
}
