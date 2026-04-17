<?php

namespace LBHurtado\Voucher\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\Voucher\Models\Voucher;

class VoucherDisbursementSucceeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Voucher $voucher,
        public PayoutRequestData $request,
        public PayoutResultData $result,
        public ?int $sliceNumber = null,
    ) {}
}