<?php

namespace LBHurtado\Voucher\Guards;

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Data\ValidationResult;
use LBHurtado\Voucher\Specifications\InputsSpecification;
use LBHurtado\Voucher\Specifications\KycSpecification;
use LBHurtado\Voucher\Specifications\LocationSpecification;
use LBHurtado\Voucher\Specifications\MobileSpecification;
use LBHurtado\Voucher\Specifications\MobileVerificationSpecification;
use LBHurtado\Voucher\Specifications\PayableSpecification;
use LBHurtado\Voucher\Specifications\SecretSpecification;
use LBHurtado\Voucher\Specifications\TimeLimitSpecification;
use LBHurtado\Voucher\Specifications\TimeWindowSpecification;

class RedemptionGuard
{
    public function __construct(
        private readonly SecretSpecification $secretSpec,
        private readonly MobileSpecification $mobileSpec,
        private readonly PayableSpecification $payableSpec,
        private readonly InputsSpecification $inputsSpec,
        private readonly KycSpecification $kycSpec,
        private readonly LocationSpecification $locationSpec,
        private readonly TimeWindowSpecification $timeWindowSpec,
        private readonly TimeLimitSpecification $timeLimitSpec,
        private readonly ?MobileVerificationSpecification $mobileVerificationSpec = null,
    ) {}

    /**
     * Check if redemption is allowed based on voucher instructions and context.
     *
     * Critical Logic:
     * - B2B vouchers (with payable): ONLY validate payable, skip all others
     * - Standard vouchers (no payable): Validate all applicable rules
     */
    public function check(object $voucher, RedemptionContext $context): ValidationResult
    {
        $failures = [];

        // B2B Voucher - ONLY validate payable
        if (($voucher->instructions->cash->validation->payable ?? null) !== null) {
            if (! $this->payableSpec->passes($voucher, $context)) {
                $failures[] = 'payable';
            }

            // Skip ALL other validations for B2B vouchers
            return new ValidationResult(empty($failures), $failures);
        }

        // Standard Voucher - Validate ALL applicable rules

        // Cash validation rules
        if (($voucher->instructions->cash->validation->secret ?? null) !== null) {
            if (! $this->secretSpec->passes($voucher, $context)) {
                $failures[] = 'secret';
            }
        }

        if (($voucher->instructions->cash->validation->mobile ?? null) !== null) {
            if (! $this->mobileSpec->passes($voucher, $context)) {
                $failures[] = 'mobile';
            }
        }

        // Input fields validation (email, name, birthdate, etc.)
        if (! empty($voucher->instructions->inputs->fields)) {
            if (! $this->inputsSpec->passes($voucher, $context)) {
                $failures[] = 'inputs';
            }
        }

        // KYC validation
        if (! $this->kycSpec->passes($voucher, $context)) {
            $failures[] = 'kyc';
        }

        // Location validation
        if (! $this->locationSpec->passes($voucher, $context)) {
            $failures[] = 'location';
        }

        // Time window validation
        if (! $this->timeWindowSpec->passes($voucher, $context)) {
            $failures[] = 'time_window';
        }

        // Time limit validation
        if (! $this->timeLimitSpec->passes($voucher, $context)) {
            $failures[] = 'time_limit';
        }

        // Mobile verification (driver-based, distinct from MobileSpecification's 1:1 match)
        if ($this->mobileVerificationSpec && ! $this->mobileVerificationSpec->passes($voucher, $context)) {
            $failures[] = 'mobile_verification';
        }

        return new ValidationResult(empty($failures), $failures);
    }
}
