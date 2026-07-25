<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class ClaimantBindingData extends Data
{
    public function __construct(
        public string $mode = 'unbound',
        public ?string $reference = null,
    ) {
        if (! in_array($this->mode, ['unbound', 'recipient'], true)) {
            throw new InvalidArgumentException(
                "Unsupported Voucher claimant binding mode [{$this->mode}].",
            );
        }

        if ($this->mode === 'recipient' && trim((string) $this->reference) === '') {
            throw new InvalidArgumentException(
                'Recipient-bound Voucher claims require a claimant reference.',
            );
        }
    }

    public static function rules(): array
    {
        return [
            'mode' => ['nullable', 'string', 'in:unbound,recipient'],
            'reference' => ['nullable', 'string', 'min:1', 'required_if:mode,recipient'],
        ];
    }
}
