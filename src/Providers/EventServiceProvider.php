<?php

namespace LBHurtado\Voucher\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    public function register()
    { /* … */
    }

    public function boot()
    {
        Event::listen(
            events: \LBHurtado\Voucher\Events\VouchersGenerated::class,
            listener: \LBHurtado\Voucher\Listeners\HandleGeneratedVouchers::class
        );
    }
}
