<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Enums;

enum VoucherSlicePlanMode: string
{
    case Equal = 'equal';
    case Flexible = 'flexible';
    case Scheduled = 'scheduled';
}
