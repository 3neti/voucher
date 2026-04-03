<?php

return [
    'updated' => [

    ],
    'post-generation' => [
        \LBHurtado\Voucher\Pipelines\GeneratedVouchers\ValidateStructure::class,
        \LBHurtado\Voucher\Pipelines\GeneratedVouchers\PopulateSettlementFields::class,
        \LBHurtado\Voucher\Pipelines\GeneratedVouchers\NormalizeMetadata::class,

        \LBHurtado\Voucher\Pipelines\GeneratedVouchers\RunFraudChecks::class,
        \LBHurtado\Voucher\Pipelines\GeneratedVouchers\ApplyUsageLimits::class,
        \LBHurtado\Voucher\Pipelines\GeneratedVouchers\CreateCashEntities::class,
        \LBHurtado\Voucher\Pipelines\GeneratedVouchers\NotifyBatchCreator::class,
        \LBHurtado\Voucher\Pipelines\GeneratedVouchers\LogAuditTrail::class,
        \LBHurtado\Voucher\Pipelines\GeneratedVouchers\MarkAsProcessed::class,
        \LBHurtado\Voucher\Pipelines\GeneratedVouchers\TriggerPostGenerationWorkflows::class,
    ],
    'mint-cash' => [
        \LBHurtado\Voucher\Pipelines\Voucher\CheckBalance::class,
        \LBHurtado\Voucher\Pipelines\Voucher\EscrowAction::class,
        \LBHurtado\Voucher\Pipelines\Voucher\PersistCash::class,
    ],
    'post-redemption' => [
        \LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedeemerAndCash::class,
        \LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash::class,
    ],
];

// put this after NormalizeMetadata
//                        \LBHurtado\Voucher\Pipelines\GeneratedVouchers\CheckFundsAvailability::class,
