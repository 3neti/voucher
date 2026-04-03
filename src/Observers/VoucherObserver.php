<?php

namespace LBHurtado\Voucher\Observers;

use LBHurtado\Voucher\Handlers\HandleRedeemedVoucher;
use LBHurtado\Voucher\Handlers\HandleRedeemingVoucher;
use LBHurtado\Voucher\Handlers\HandleShouldMarkRedeemedVoucher;
use LBHurtado\Voucher\Handlers\HandleUpdatedVoucher;
use LBHurtado\Voucher\Models\Voucher;

class VoucherObserver
{
    public function created(Voucher $voucher): void {}

    public function updated(Voucher $voucher): void
    {
        app(HandleUpdatedVoucher::class)->handle($voucher);
    }

    public function deleted(Voucher $voucher): void {}

    public function restored(Voucher $voucher): void {}

    public function forceDeleted(Voucher $voucher): void {}

    /**
     * Handle the Voucher "redeeming" event.
     */
    public function redeeming(Voucher $voucher): void
    {
        app(HandleRedeemingVoucher::class)->handle($voucher);
    }

    /**
     * Handle the Voucher "shouldMarkRedeemed" event.
     */
    public function shouldMarkRedeemed(Voucher $voucher): void
    {
        app(HandleShouldMarkRedeemedVoucher::class)->handle($voucher);
    }

    /**
     * Handle the Voucher "redeemed" event.
     */
    public function redeemed(Voucher $voucher): void
    {
        app(HandleRedeemedVoucher::class)->handle($voucher);
    }
}
