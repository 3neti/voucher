<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Exceptions;

use InvalidArgumentException;

final class UnsupportedVoucherSlicePlanSchemaException extends InvalidArgumentException
{
    public static function forSchema(string $schema): self
    {
        return new self("Unsupported voucher slice plan schema [{$schema}].");
    }
}
