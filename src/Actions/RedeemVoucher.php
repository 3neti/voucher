<?php

namespace LBHurtado\Voucher\Actions;

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Contracts\RedeemsVouchers;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Services\ExecutionEngine;
use Lorisleiva\Actions\Concerns\AsAction;

class RedeemVoucher implements RedeemsVouchers
{
    use AsAction;

    public function __construct(
        private readonly ExecutionEngine $executionEngine,
    ) {}

    /**
     * Attempt to redeem a voucher for a given contact.
     *
     * @param  Contact  $contact
     * @param  string  $voucher_code
     * @param  array  $meta
     * @return bool
     *
     */
    public function handle(Contact $contact, string $voucher_code, array $meta = []): bool
    {
        return $this->executionEngine->execute(new ExecutionContextData(
            contact: $contact,
            voucherCode: $voucher_code,
            meta: $meta,
        ))->successful;
    }
}
