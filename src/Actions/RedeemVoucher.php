<?php

namespace LBHurtado\Voucher\Actions;

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Contracts\RedeemsVouchers;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\ExecutionEngine;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedeemVoucher implements RedeemsVouchers
{
    use AsAction;

    public function __construct(
        private readonly ExecutionEngine $executionEngine,
    ) {}

    /**
     * Attempt to redeem a voucher for a given contact.
     */
    public function handle(Contact $contact, string $voucher_code, array $meta = []): bool
    {
        return $this->executionEngine->execute($this->contextFor(
            contact: $contact,
            voucherCode: $voucher_code,
            meta: $meta,
        ))->successful;
    }

    private function contextFor(Contact $contact, string $voucherCode, array $meta = []): ExecutionContextData
    {
        try {
            $voucher = Voucher::query()
                ->where('code', strtoupper(trim($voucherCode)))
                ->first();

            if ($voucher instanceof Voucher) {
                return ExecutionContextData::fromRedemption(
                    voucher: $voucher,
                    contact: $contact,
                    voucherCode: (string) $voucher->code,
                    meta: $meta,
                    correlation: $this->correlationFrom($meta),
                );
            }
        } catch (Throwable) {
            // Preserve the legacy public redemption path if persisted context cannot be hydrated.
        }

        return new ExecutionContextData(
            contact: $contact,
            voucherCode: $voucherCode,
            meta: $meta,
            correlation: $this->correlationFrom($meta),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function correlationFrom(array $meta): array
    {
        return array_filter([
            'execution_id' => $meta['execution_id'] ?? null,
            'request_id' => $meta['request_id'] ?? null,
            'correlation_id' => $meta['correlation_id'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }
}
