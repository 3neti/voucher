<?php

namespace LBHurtado\Voucher\Contracts;

use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Models\Voucher;

interface RedemptionRuleValidator
{
    public function supports(Voucher $voucher): bool;

    /**
     * @return array<int, \LBHurtado\Voucher\Data\RedemptionValidationIssueData>
     */
    public function validate(Voucher $voucher, RedemptionEvidenceData $evidence): array;
}