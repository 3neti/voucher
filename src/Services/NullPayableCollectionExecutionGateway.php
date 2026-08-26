<?php

namespace LBHurtado\Voucher\Services;

use LBHurtado\Voucher\Contracts\PayableCollectionExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\PayableCollectionRejectedException;

class NullPayableCollectionExecutionGateway implements PayableCollectionExecutionGateway
{
    public function authorize(ExecutionContextData $context, string $executionId): array
    {
        throw new PayableCollectionRejectedException(
            'No payable collection execution gateway is configured.',
        );
    }

    public function credit(
        ExecutionContextData $context,
        int $amountMinor,
        string $providerTransactionId,
        string $executionId,
    ): array {
        throw new PayableCollectionRejectedException(
            'No payable collection execution gateway is configured.',
        );
    }
}
