<?php

namespace LBHurtado\Voucher\Validators;

use LBHurtado\Voucher\Contracts\RedemptionRuleValidator;
use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Data\RedemptionValidationIssueData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;

class FaceMatchRuleValidator implements RedemptionRuleValidator
{
//    public function supports(Voucher $voucher): bool
//    {
//        $faceMatchRequired = (bool) $voucher->instructions?->validation?->face_match?->required;
//
//        $fields = collect($voucher->instructions?->inputs?->fields ?? [])
//            ->map(function ($field) {
//                if ($field instanceof VoucherInputField) {
//                    return $field->value;
//                }
//
//                return is_string($field) ? $field : null;
//            })
//            ->filter(fn ($value) => $value !== null)
//            ->values();
//
//        $kycInputRequired = $fields->contains(VoucherInputField::KYC->value);
//
//        return $faceMatchRequired || $kycInputRequired;
//    }
//    public function supports(Voucher $voucher): bool
//    {
//        return $voucher->instructions?->validation?->face_match !== null;
//    }

    public function supports(Voucher $voucher): bool
    {
        return (bool) $voucher->instructions?->validation?->face_match?->required;
    }

    public function validate(Voucher $voucher, RedemptionEvidenceData $evidence): array
    {
        $mode = $voucher->instructions?->validation?->face_match?->on_failure ?? 'block';

        $severity = $mode === 'warn'
            ? RedemptionValidationSeverity::WARN
            : RedemptionValidationSeverity::BLOCK;

        if ($evidence->face_verification_verified !== true || $evidence->face_match !== true) {
            return [
                new RedemptionValidationIssueData(
                    field: 'face_match',
                    code: RedemptionValidationCode::FACE_MATCH_NOT_VERIFIED,
                    severity: $severity,
                    message: 'Required face verification is missing or failed.',
                    context: [
                        'verified' => $evidence->face_verification_verified,
                        'face_match' => $evidence->face_match,
                        'match_confidence' => $evidence->match_confidence,
                        'verified_at' => $evidence->face_verified_at?->toIso8601String(),
                        'failure_reason' => $evidence->face_failure_reason,
                    ],
                ),
            ];
        }

        $minConfidence = $voucher->instructions?->validation?->face_match?->min_confidence;

        if (
            $minConfidence !== null
            && $evidence->match_confidence !== null
            && $evidence->match_confidence < $minConfidence
        ) {
            return [
                new RedemptionValidationIssueData(
                    field: 'face_match',
                    code: RedemptionValidationCode::FACE_MATCH_CONFIDENCE_TOO_LOW,
                    severity: $severity,
                    message: 'Face match confidence is below the required threshold.',
                    context: [
                        'match_confidence' => $evidence->match_confidence,
                        'min_confidence' => $minConfidence,
                    ],
                ),
            ];
        }

        return [];
    }
}