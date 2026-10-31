<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class ExecutionSettlementData extends Data
{
    public function __construct(
        public string $driver,
        public ?string $reference = null,
        public ?string $version = null,
        public ?string $readiness_gate = null,
        public ?string $payment_requirement = null,
    ) {}
}
