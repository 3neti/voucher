<?php

namespace LBHurtado\Voucher\Contracts;

use Illuminate\Support\Collection;

/**
 * Contract for voucher generation notifications.
 *
 * Host applications should implement this interface and bind it in their service provider
 * to provide custom notification behavior when vouchers are generated.
 */
interface VouchersGeneratedNotificationInterface
{
    /**
     * Create a new notification instance.
     *
     * @param  Collection  $vouchers  Collection of generated vouchers
     */
    public static function make(Collection $vouchers): static;
}
