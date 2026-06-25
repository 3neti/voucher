<?php

namespace LBHurtado\Voucher\Services;

use LBHurtado\Voucher\Contracts\RedeemsVouchers;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;

class ExecutionEngine
{
    public function __construct(
        private readonly RedeemsVouchers $redeemer,
    ) {}

    public function driverKeyFor(ExecutionContextData $context): string
    {
        return $context->instruction?->driver ?: 'default';
    }

    public function execute(ExecutionContextData $context): ExecutionResultData
    {
        $driver = $this->driverKeyFor($context);
        $metadata = $this->metadataFor($context, $driver);

        $successful = $this->redeemer->handle(
            $context->contact,
            $context->voucherCode,
            $context->meta,
        );

        if ($successful) {
            return ExecutionResultData::succeeded($driver, $metadata);
        }

        return ExecutionResultData::failed(
            driver: $driver,
            failure: 'compatibility_redemption_rejected',
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataFor(ExecutionContextData $context, string $driver): array
    {
        return [
            'voucher_code' => $context->voucherCode,
            'voucher_id' => $context->voucher?->getKey(),
            'contact_id' => $context->contact->getKey(),
            'driver' => $driver,
            'correlation' => $context->correlation,
        ];
    }
}
