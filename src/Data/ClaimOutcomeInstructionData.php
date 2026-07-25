<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class ClaimOutcomeInstructionData extends Data
{
    public function __construct(
        public string $key,
        public ?string $pricing_profile = null,
        public ?array $requirements = null,
    ) {}

    public static function rules(): array
    {
        return [
            'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'pricing_profile' => ['nullable', 'string', 'min:1'],
            'requirements' => ['nullable', 'array'],
        ];
    }
}
