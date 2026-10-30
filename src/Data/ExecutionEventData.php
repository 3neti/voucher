<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class ExecutionEventData extends Data
{
    /** @param list<string> $requires */
    public function __construct(
        public string $actor,
        public array $requires = [],
        public ?string $surface = null,
        public ?string $authentication = null,
    ) {}
}
