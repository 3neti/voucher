<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Enums;

enum VoucherSliceSelectionPolicy: string
{
    case NextOnly = 'next_only';
    case One = 'one';
    case OneOrMany = 'one_or_many';
    case FlexibleAmount = 'flexible_amount';
}
