<?php

namespace LBHurtado\Voucher\Http\Controllers;

use Illuminate\Http\Request;
use LBHurtado\Voucher\Models\Voucher;

class VoucherDataController
{
    public function __invoke(Request $request, string $code)
    {
        $voucher = Voucher::where('code', $code)->firstOrFail();

        return $voucher->getData();
    }
}
