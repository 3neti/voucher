<?php

namespace LBHurtado\Voucher\Pipelines\RedeemedVoucher;

use Closure;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Exceptions\VoucherRedemptionContractViolationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\RedemptionContractEngine;

class ValidateRedemptionContract
{
    public function __construct(
        protected RedemptionContractEngine $engine,
    ) {}

    public function handle(Voucher $voucher, Closure $next): Voucher
    {
        $validation = $voucher->instructions?->validation;

        if (! $validation) {
            return $next($voucher);
        }

        $result = $this->engine->validate($voucher);

        if ($result->passed) {
            return $next($voucher);
        }

        $this->persistValidationResult($voucher, $result);

        if (! $result->should_block) {
            Log::warning('[ValidateRedemptionContract] Validation warnings only.', [
                'voucher_code' => $voucher->code,
                'issues' => collect($result->issues)->map(fn ($issue) => [
                    'field' => $issue->field,
                    'code' => $issue->code->value,
                    'severity' => $issue->severity->value,
                ])->all(),
            ]);

            return $next($voucher);
        }

        throw new VoucherRedemptionContractViolationException(
            violations: collect($result->issues)->mapWithKeys(
                fn ($issue) => [$issue->field => $issue->code->value]
            )->all()
        );
    }

    protected function persistValidationResult(Voucher $voucher, $result): void
    {
        $metadata = $voucher->metadata ?? [];

        $metadata['redemption_validation'] = [
            'passed' => $result->passed,
            'should_block' => $result->should_block,
            'checked_at' => $result->checked_at,
            'issues' => collect($result->issues)->map(fn ($issue) => [
                'field' => $issue->field,
                'code' => $issue->code->value,
                'severity' => $issue->severity->value,
                'message' => $issue->message,
                'context' => $issue->context,
            ])->values()->all(),
            'violations' => collect($result->issues)->mapWithKeys(
                fn ($issue) => [$issue->field => $issue->code->value]
            )->all(),
        ];

        $voucher->metadata = $metadata;
        $voucher->save();
    }
}