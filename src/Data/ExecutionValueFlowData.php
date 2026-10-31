<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class ExecutionValueFlowData extends Data
{
    public function __construct(
        public ?string $source = null,
        public ?string $destination = null,
    ) {}
}
