<?php

namespace LBHurtado\Voucher\Specifications;

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Enums\VoucherInputField;

/**
 * Validates that all required input fields from form flow were collected.
 *
 * This ensures that the inputs in RedemptionContext match the required
 * fields defined in voucher.instructions.inputs.fields
 */
class InputsSpecification implements RedemptionSpecificationInterface
{
    public function passes(object $voucher, RedemptionContext $context): bool
    {
        $requiredFields = $voucher->instructions->inputs->fields ?? [];

        // If no fields required, pass
        if (empty($requiredFields)) {
            return true;
        }

        // Get collected inputs from context
        $collectedInputs = $context->inputs;

        // Convert enum fields to strings for comparison
        $requiredFieldNames = array_map(
            fn ($field) => $field instanceof VoucherInputField ? $field->value : $field,
            $requiredFields
        );

        // Check each required field is present in collected inputs
        foreach ($requiredFieldNames as $fieldName) {
            // Skip special fields that are handled by other specifications
            if ($this->isSpecialField($fieldName)) {
                continue;
            }

            // Check if field exists and is not empty
            if (! isset($collectedInputs[$fieldName]) || $this->isEmpty($collectedInputs[$fieldName])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if field is handled by a dedicated specification.
     *
     * Special fields like 'kyc', 'location', 'selfie', 'signature'
     * have their own specifications and shouldn't be checked here.
     */
    private function isSpecialField(string $fieldName): bool
    {
        return in_array($fieldName, [
            'kyc',          // KycSpecification
            'location',     // LocationSpecification
            'selfie',       // Handled by form flow, just presence check
            'signature',    // Handled by form flow, just presence check
        ]);
    }

    /**
     * Check if a value is considered empty.
     */
    private function isEmpty($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    /**
     * Get list of missing fields for error reporting.
     */
    public function getMissingFields(object $voucher, RedemptionContext $context): array
    {
        $requiredFields = $voucher->instructions->inputs->fields ?? [];
        $collectedInputs = $context->inputs;
        $missing = [];

        $requiredFieldNames = array_map(
            fn ($field) => $field instanceof VoucherInputField ? $field->value : $field,
            $requiredFields
        );

        foreach ($requiredFieldNames as $fieldName) {
            if ($this->isSpecialField($fieldName)) {
                continue;
            }

            if (! isset($collectedInputs[$fieldName]) || $this->isEmpty($collectedInputs[$fieldName])) {
                $missing[] = $fieldName;
            }
        }

        return $missing;
    }
}
