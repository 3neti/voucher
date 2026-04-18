<?php

namespace LBHurtado\Voucher\Validators;

use LBHurtado\Voucher\Contracts\RedemptionRuleValidator;
use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Data\RedemptionValidationIssueData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Models\Voucher;

class RequiredInputFieldsValidator implements RedemptionRuleValidator
{
    public function supports(Voucher $voucher): bool
    {
        $fields = collect($voucher->instructions?->inputs?->fields ?? [])
            ->map(function ($field) {
                if ($field instanceof VoucherInputField) {
                    return $field->value;
                }

                return is_string($field) ? $field : null;
            })
            ->filter(fn ($value) => $value !== null)
            ->values();

        return $fields->isNotEmpty();
    }

    public function validate(Voucher $voucher, RedemptionEvidenceData $evidence): array
    {
        $fields = collect($voucher->instructions?->inputs?->fields ?? [])
            ->map(function ($field) {
                if ($field instanceof VoucherInputField) {
                    return $field->value;
                }

                return is_string($field) ? $field : null;
            })
            ->filter(fn ($value) => $value !== null)
            ->values();

        $issues = [];

        foreach ($fields as $field) {
            if ($this->isFieldPresent($field, $evidence)) {
                continue;
            }

            $issues[] = new RedemptionValidationIssueData(
                field: $field,
                code: RedemptionValidationCode::REQUIRED_INPUT_MISSING,
                severity: RedemptionValidationSeverity::BLOCK,
                message: "Required input [{$field}] is missing.",
            );
        }

        return $issues;
    }

    protected function isFieldPresent(string $field, RedemptionEvidenceData $evidence): bool
    {
        return match ($field) {
            VoucherInputField::SIGNATURE->value => $this->hasString($evidence->signature),
            VoucherInputField::SELFIE->value => $this->hasString($evidence->selfie),
            VoucherInputField::OTP->value => $this->hasString($evidence->otp),
            VoucherInputField::LOCATION->value => $evidence->latitude !== null && $evidence->longitude !== null,
            VoucherInputField::REFERENCE_CODE->value => $this->hasString($evidence->reference_code),
            VoucherInputField::MOBILE->value => $this->hasString($evidence->mobile),
            VoucherInputField::EMAIL->value => $this->hasString($evidence->email),
            VoucherInputField::NAME->value => $this->hasString($evidence->name),
            VoucherInputField::ADDRESS->value => $this->hasString($evidence->address),
            VoucherInputField::BIRTH_DATE->value => $this->hasString($evidence->birth_date),
            VoucherInputField::GROSS_MONTHLY_INCOME->value => $this->hasString($evidence->gross_monthly_income),
            VoucherInputField::KYC->value => is_array($evidence->kyc) && $evidence->kyc !== [],
            default => false,
        };
    }

    protected function hasString(?string $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}