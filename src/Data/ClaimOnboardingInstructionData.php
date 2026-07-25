<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class ClaimOnboardingInstructionData extends Data
{
    public function __construct(
        public string $mode = 'if_required',
        public ?string $profile = null,
    ) {
        if (! in_array($this->mode, ['never', 'if_required', 'required'], true)) {
            throw new InvalidArgumentException(
                "Unsupported Voucher claim onboarding mode [{$this->mode}].",
            );
        }

        if ($this->profile !== null && trim($this->profile) === '') {
            throw new InvalidArgumentException(
                'Voucher claim onboarding profiles cannot be empty.',
            );
        }
    }

    public static function rules(): array
    {
        return [
            'mode' => ['nullable', 'string', 'in:never,if_required,required'],
            'profile' => ['nullable', 'string', 'min:1'],
        ];
    }
}
