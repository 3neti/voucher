<?php

namespace LBHurtado\Voucher\Validators;

use LBHurtado\Voucher\Contracts\RedemptionRuleValidator;
use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Data\RedemptionValidationIssueData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Models\Voucher;

class SignatureRuleValidator implements RedemptionRuleValidator
{
    public function supports(Voucher $voucher): bool
    {
        return (bool) $voucher->instructions?->validation?->signature?->required;
    }

    public function validate(Voucher $voucher, RedemptionEvidenceData $evidence): array
    {
        if (is_string($evidence->signature) && trim($evidence->signature) !== '') {
            return [];
        }

        $mode = $voucher->instructions?->validation?->signature?->on_failure ?? 'block';

        return [
            new RedemptionValidationIssueData(
                field: 'signature',
                code: RedemptionValidationCode::MISSING,
                severity: $mode === 'warn'
                    ? RedemptionValidationSeverity::WARN
                    : RedemptionValidationSeverity::BLOCK,
                message: 'Required signature is missing.',
            ),
        ];
    }
}