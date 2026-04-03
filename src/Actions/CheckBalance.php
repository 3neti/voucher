<?php

namespace LBHurtado\Voucher\Actions;

use Brick\Money\Money;
use LBHurtado\Voucher\Models\MoneyIssuer;
use Lorisleiva\Actions\Concerns\AsAction;

/** @deprecated  */
class CheckBalance
{
    use AsAction;

    public function handle(MoneyIssuer $issuer, string $account): Money
    {
        return Money::of(0, 'PHP');
    }
}
