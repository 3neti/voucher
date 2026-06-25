<?php

namespace LBHurtado\Voucher\Contracts;

use LBHurtado\Contact\Models\Contact;

interface RedeemsVouchers
{
    public function handle(Contact $contact, string $voucher_code, array $meta = []): bool;
}
