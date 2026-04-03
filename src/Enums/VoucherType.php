<?php

namespace LBHurtado\Voucher\Enums;

enum VoucherType: string
{
    case REDEEMABLE = 'redeemable';  // One-shot payout only
    case PAYABLE = 'payable';        // Accept payments only
    case SETTLEMENT = 'settlement';   // Bidirectional (pay and redeem)
}
