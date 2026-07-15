<?php

namespace LBHurtado\Voucher\Exceptions;

use RuntimeException;

class UnsupportedExecutionInstructionSchemaException extends RuntimeException
{
    public static function forSchema(string $schema): self
    {
        return new self("Unsupported execution instruction schema [{$schema}].");
    }
}
