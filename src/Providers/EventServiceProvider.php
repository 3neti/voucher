<?php

namespace LBHurtado\Voucher\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use LBHurtado\Voucher\Events\VouchersGenerated;
use LBHurtado\Voucher\Listeners\HandleGeneratedVouchers;

class EventServiceProvider extends ServiceProvider
{
    public function register()
    { /* … */
    }

    public function boot()
    {
        Event::listen(
            events: VouchersGenerated::class,
            listener: HandleGeneratedVouchers::class
        );
    }
}
