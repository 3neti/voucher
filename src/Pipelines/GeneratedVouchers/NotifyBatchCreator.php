<?php

namespace LBHurtado\Voucher\Pipelines\GeneratedVouchers;

use Closure;
use LBHurtado\Voucher\Contracts\VouchersGeneratedNotificationInterface;

class NotifyBatchCreator
{
    public function handle($vouchers, Closure $next)
    {
        $first = $vouchers->first();
        $owner = $first->owner;

        // Send SMS notification to voucher owner (issuer) if interface is bound
        if ($owner && $owner->mobile && app()->bound(VouchersGeneratedNotificationInterface::class)) {
            $notificationClass = app(VouchersGeneratedNotificationInterface::class);
            $notification = $notificationClass::make($vouchers);

            $owner->notify($notification);
        }

        // TODO: Email notification support (future)
        // if ($owner && $owner->email && app()->bound(VouchersGeneratedNotificationInterface::class)) {
        //     $notificationClass = app(VouchersGeneratedNotificationInterface::class);
        //     $notification = $notificationClass::make($vouchers);
        //
        //     $owner->notify($notification);
        // }

        return $next($vouchers);
    }
}
