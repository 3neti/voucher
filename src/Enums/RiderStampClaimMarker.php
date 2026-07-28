<?php

namespace LBHurtado\Voucher\Enums;

enum RiderStampClaimMarker: string
{
    case None = 'none';
    case Code = 'code';
    case Qr = 'qr';
    case Both = 'both';
}
