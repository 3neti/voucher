<?php

namespace LBHurtado\Voucher\Enums;

enum VoucherState: string
{
    case ACTIVE = 'active';
    case LOCKED = 'locked';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
}
