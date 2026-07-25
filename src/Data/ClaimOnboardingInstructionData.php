<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class ClaimOnboardingInstructionData extends Data
{
    public function __construct(
        public string $mode = 'if_required',
        public ?string $profile = null,
    ) {}

    public static function rules(): array
    {
        return [
            'mode' => ['nullable', 'string', 'in:never,if_required,required'],
            'profile' => ['nullable', 'string', 'min:1'],
        ];
    }
}
