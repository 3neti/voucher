<?php

namespace LBHurtado\Voucher\Contracts;

use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Data\RedemptionValidationIssueData;
use LBHurtado\Voucher\Models\Voucher;

interface RedemptionRuleValidator
{
    public function supports(Voucher $voucher): bool;

    /**
     * @return array<int, RedemptionValidationIssueData>
     */
    public function validate(Voucher $voucher, RedemptionEvidenceData $evidence): array;
}
