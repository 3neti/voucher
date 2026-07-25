<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class ClaimantBindingData extends Data
{
    public function __construct(
        public string $mode = 'unbound',
        public ?string $reference = null,
    ) {}

    public static function rules(): array
    {
        return [
            'mode' => ['nullable', 'string', 'in:unbound,recipient'],
            'reference' => ['nullable', 'string', 'min:1', 'required_if:mode,recipient'],
        ];
    }
}
