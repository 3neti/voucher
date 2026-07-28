<?php

namespace LBHurtado\Voucher\Enums;

enum RiderStampSource: string
{
    case Automatic = 'automatic';
    case Message = 'message';
    case Url = 'url';
    case Splash = 'splash';
}
