<?php

namespace LBHurtado\Voucher\Exceptions;

use RuntimeException;

class UnknownExecutionPipelineStepException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Unknown execution pipeline step [{$key}].");
    }
}
