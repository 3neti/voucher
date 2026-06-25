<?php

namespace LBHurtado\Voucher\Contracts;

use Illuminate\Support\Collection;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

interface GeneratesVouchers
{
    public function handle(VoucherInstructionsData|array $instructions): Collection;
}
