<?php

namespace LBHurtado\Voucher\Validators;

use LBHurtado\Voucher\Contracts\RedemptionRuleValidator;
use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Data\RedemptionValidationIssueData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Models\Voucher;

class OtpRuleValidator implements RedemptionRuleValidator
{
    public function supports(Voucher $voucher): bool
    {
        return (bool) $voucher->instructions?->validation?->otp?->required;
    }

    public function validate(Voucher $voucher, RedemptionEvidenceData $evidence): array
    {
        if ($evidence->otp_verified === true) {
            return [];
        }

        $mode = $voucher->instructions?->validation?->otp?->on_failure ?? 'block';

        return [
            new RedemptionValidationIssueData(
                field: 'otp',
                code: RedemptionValidationCode::OTP_NOT_VERIFIED,
                severity: $mode === 'warn'
                    ? RedemptionValidationSeverity::WARN
                    : RedemptionValidationSeverity::BLOCK,
                message: 'Required OTP verification is missing or failed.',
                context: [
                    'otp_verified' => $evidence->otp_verified,
                    'otp_verified_at' => $evidence->otp_verified_at?->toIso8601String(),
                ],
            ),
        ];
    }
}