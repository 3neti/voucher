<?php

namespace LBHurtado\Voucher\Validators;

use LBHurtado\Voucher\Contracts\RedemptionRuleValidator;
use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Data\RedemptionValidationIssueData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Support\RedemptionLocationValidator;

class LocationRuleValidator implements RedemptionRuleValidator
{
    public function __construct(
        protected ?RedemptionLocationValidator $locationValidator = null,
    ) {
        $this->locationValidator ??= new RedemptionLocationValidator;
    }

    public function supports(Voucher $voucher): bool
    {
        return (bool) $voucher->instructions?->validation?->location?->required;
    }

    public function validate(Voucher $voucher, RedemptionEvidenceData $evidence): array
    {
        $rule = $voucher->instructions?->validation?->location;
        $mode = $rule?->on_failure ?? 'block';

        $severity = $mode === 'warn'
            ? RedemptionValidationSeverity::WARN
            : RedemptionValidationSeverity::BLOCK;

        if ($evidence->latitude === null || $evidence->longitude === null) {
            return [
                new RedemptionValidationIssueData(
                    field: 'location',
                    code: RedemptionValidationCode::MISSING,
                    severity: $severity,
                    message: 'Required redemption location is missing.',
                ),
            ];
        }

        if (
            $rule?->target_lat !== null &&
            $rule?->target_lng !== null &&
            $rule?->radius_meters !== null
        ) {
            $withinRadius = $this->locationValidator->isWithinRadius(
                $evidence->latitude,
                $evidence->longitude,
                (float) $rule->target_lat,
                (float) $rule->target_lng,
                (int) $rule->radius_meters,
            );

            if (! $withinRadius) {
                return [
                    new RedemptionValidationIssueData(
                        field: 'location',
                        code: RedemptionValidationCode::OUTSIDE_RADIUS,
                        severity: $severity,
                        message: 'Redemption location is outside the allowed radius.',
                        context: [
                            'latitude' => $evidence->latitude,
                            'longitude' => $evidence->longitude,
                            'target_lat' => $rule->target_lat,
                            'target_lng' => $rule->target_lng,
                            'radius_meters' => $rule->radius_meters,
                        ],
                    ),
                ];
            }
        }

        return [];
    }
}