<?php

namespace LBHurtado\Voucher\Enums;

enum RiderStampCopySource: string
{
    case Automatic = 'automatic';
    case Message = 'message';
    case Url = 'url';
    case Splash = 'splash';
    case Custom = 'custom';
    case None = 'none';
}
