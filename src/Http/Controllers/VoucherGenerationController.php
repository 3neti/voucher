<?php

namespace LBHurtado\Voucher\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;

class VoucherGenerationController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = VoucherInstructionsData::from($request->all());

        $vouchers = GenerateVouchers::run($data);

        return response()->json([
            'message' => 'Vouchers successfully generated.',
            'data' => $vouchers->map(fn (Voucher $voucher) => [
                'code' => $voucher->code,
                'metadata' => $voucher->metadata,
            ]),
        ]);
    }
}
