<?php

use LBHurtado\Voucher\Pipelines\GeneratedVouchers\ApplyUsageLimits;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\CreateCashEntities;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\LogAuditTrail;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\MarkAsProcessed;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\NormalizeMetadata;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\NotifyBatchCreator;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\PopulateSettlementFields;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\RunFraudChecks;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\TriggerPostGenerationWorkflows;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\ValidateStructure;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedeemerAndCash;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract;
use LBHurtado\Voucher\Pipelines\Voucher\CheckBalance;
use LBHurtado\Voucher\Pipelines\Voucher\EscrowAction;
use LBHurtado\Voucher\Pipelines\Voucher\PersistCash;

return [
    'updated' => [

    ],
    'post-generation' => [
        ValidateStructure::class,
        PopulateSettlementFields::class,
        NormalizeMetadata::class,

        RunFraudChecks::class,
        ApplyUsageLimits::class,
        CreateCashEntities::class,
        NotifyBatchCreator::class,
        LogAuditTrail::class,
        MarkAsProcessed::class,
        TriggerPostGenerationWorkflows::class,
    ],
    'mint-cash' => [
        CheckBalance::class,
        EscrowAction::class,
        PersistCash::class,
    ],
    'post-redemption' => [
        ValidateRedeemerAndCash::class,
        ValidateRedemptionContract::class,
        DisburseCash::class,
    ],
];

// put this after NormalizeMetadata
//                        \LBHurtado\Voucher\Pipelines\GeneratedVouchers\CheckFundsAvailability::class,
