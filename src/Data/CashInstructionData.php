<?php

namespace LBHurtado\Voucher\Data;

use Brick\Money\Money;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class CashInstructionData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public float $amount,
        public string $currency,
        public CashValidationRulesData $validation,
        #[WithCast(EnumCast::class)]
        public ?SettlementRail $settlement_rail = null,
        public string $fee_strategy = 'absorb',
        public ?string $slice_mode = null,
        public ?int $slices = null,
        public ?int $max_slices = null,
        public ?float $min_withdrawal = null,
        public ?string $type = null,
        public ?array $mandates = [],
    ) {
        $this->applyRulesAndDefaults();
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'amount' => [
                ['required', 'numeric', 'min:0'],
                config('instructions.cash.amount'),
            ],
            'currency' => [
                ['required', 'string', 'size:3'],
                config('instructions.cash.currency'),
            ],
            'settlement_rail' => [
                ['nullable'],
                null,
            ],
            'fee_strategy' => [
                ['required', 'string', 'in:absorb,include,add'],
                'absorb',
            ],
            'slice_mode' => [
                ['nullable', 'string', 'in:fixed,open'],
                null,
            ],
            'slices' => [
                ['nullable', 'integer', 'min:1'],
                null,
            ],
            'max_slices' => [
                ['nullable', 'integer', 'min:1'],
                null,
            ],
            'min_withdrawal' => [
                ['nullable', 'numeric', 'min:0'],
                null,
            ],
        ];
    }

    public function getAmount(): Money
    {
        return Money::of($this->amount, $this->currency);
    }
}