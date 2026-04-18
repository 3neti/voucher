<?php

namespace LBHurtado\Voucher\Enums;

enum RedemptionValidationSeverity: string
{
    case WARN = 'warn';
    case BLOCK = 'block';
}