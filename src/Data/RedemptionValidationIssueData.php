<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use Spatie\LaravelData\Data;

class RedemptionValidationIssueData extends Data
{
    public function __construct(
        public string $field,
        public RedemptionValidationCode $code,
        public RedemptionValidationSeverity $severity,
        public string $message,
        public ?array $context = null,
    ) {}
}