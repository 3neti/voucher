<?php

namespace LBHurtado\Voucher\Exceptions;

use RuntimeException;

class UnknownExecutionDriverException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Unknown execution driver [{$key}].");
    }
}
