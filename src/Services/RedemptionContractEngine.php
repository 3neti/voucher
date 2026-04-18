<?php

namespace LBHurtado\Voucher\Services;

use LBHurtado\Voucher\Contracts\RedemptionRuleValidator;
use LBHurtado\Voucher\Data\RedemptionValidationResultData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Support\RedemptionEvidenceExtractor;

class RedemptionContractEngine
{
    /**
     * @param  array<int, RedemptionRuleValidator>  $validators
     */
    public function __construct(
        protected RedemptionEvidenceExtractor $extractor,
        protected array $validators = [],
    ) {}

    public function validate(Voucher $voucher): RedemptionValidationResultData
    {
        $evidence = $this->extractor->extract($voucher);

        $issues = [];

        foreach ($this->validators as $validator) {
            if (! $validator->supports($voucher)) {
                continue;
            }

            $issues = [...$issues, ...$validator->validate($voucher, $evidence)];
        }

        $shouldBlock = collect($issues)->contains(
            fn ($issue) => $issue->severity->value === 'block'
        );

        return new RedemptionValidationResultData(
            passed: count($issues) === 0,
            should_block: $shouldBlock,
            issues: $issues,
            checked_at: now()->toIso8601String(),
        );
    }
}