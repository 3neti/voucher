<?php

namespace LBHurtado\Voucher\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\Voucher\Models\Voucher;
use Throwable;

class VoucherDisbursementFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Voucher $voucher,
        public PayoutRequestData $request,
        public Throwable $exception,
        public ?int $sliceNumber = null,
    ) {}
}