<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class ClaimDocumentInstructionData extends Data
{
    /** @param list<string> $accept */
    public function __construct(
        public string $key,
        public string $label,
        public array $accept,
        public bool $required,
    ) {}
}
