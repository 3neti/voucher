<?php

namespace LBHurtado\Voucher\Validators;

use LBHurtado\Voucher\Contracts\RedemptionRuleValidator;
use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Data\RedemptionValidationIssueData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Models\Voucher;

class SelfieRuleValidator implements RedemptionRuleValidator
{
    public function supports(Voucher $voucher): bool
    {
        return (bool) $voucher->instructions?->validation?->selfie?->required;
    }

    public function validate(Voucher $voucher, RedemptionEvidenceData $evidence): array
    {
        if (is_string($evidence->selfie) && trim($evidence->selfie) !== '') {
            return [];
        }

        $mode = $voucher->instructions?->validation?->selfie?->on_failure ?? 'block';

        return [
            new RedemptionValidationIssueData(
                field: 'selfie',
                code: RedemptionValidationCode::MISSING,
                severity: $mode === 'warn'
                    ? RedemptionValidationSeverity::WARN
                    : RedemptionValidationSeverity::BLOCK,
                message: 'Required selfie is missing.',
            ),
        ];
    }
}