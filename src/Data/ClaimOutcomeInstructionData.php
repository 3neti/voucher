<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class ClaimOutcomeInstructionData extends Data
{
    public function __construct(
        public string $key,
        public ?string $pricing_profile = null,
        public ?array $requirements = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $this->key) !== 1) {
            throw new InvalidArgumentException(
                'Voucher claim outcome keys must use lowercase snake case.',
            );
        }

        if ($this->pricing_profile !== null && trim($this->pricing_profile) === '') {
            throw new InvalidArgumentException(
                'Voucher claim outcome pricing profiles cannot be empty.',
            );
        }
    }

    public static function rules(): array
    {
        return [
            'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'pricing_profile' => ['nullable', 'string', 'min:1'],
            'requirements' => ['nullable', 'array'],
        ];
    }
}
