<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

class RedemptionValidationResultData extends Data
{
    /**
     * @param  array<int, RedemptionValidationIssueData>  $issues
     */
    public function __construct(
        public bool $passed,
        public bool $should_block,
        public array $issues,
        public string $checked_at,
    ) {}
}