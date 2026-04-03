<?php

namespace LBHurtado\Voucher\Handlers;

use LBHurtado\Voucher\Models\Voucher;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleRedeemingVoucher
{
    use AsAction;

    public function handle(Voucher $voucher): void {}
}
