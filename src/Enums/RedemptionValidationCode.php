<?php

namespace LBHurtado\Voucher\Enums;

enum RedemptionValidationCode: string
{
    case MISSING = 'missing';
    case OUTSIDE_RADIUS = 'outside_radius';
    case INVALID = 'invalid';
}