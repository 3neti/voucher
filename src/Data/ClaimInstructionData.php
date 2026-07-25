<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class ClaimInstructionData extends Data
{
    public const SCHEMA = 'voucher.claim.v1';

    /**
     * @param  list<ClaimOutcomeInstructionData>  $outcomes
     */
    public function __construct(
        public array $outcomes,
        public string $selection = 'claimant',
        public string $consumption = 'one_of',
        public ?string $default_outcome = null,
        public ?ClaimOnboardingInstructionData $onboarding = null,
        public ?ClaimantBindingData $claimant = null,
        public string $profile = self::SCHEMA,
    ) {
        if ($this->outcomes === []) {
            throw new InvalidArgumentException(
                'Voucher claim instructions require at least one outcome.',
            );
        }

        foreach ($this->outcomes as $outcome) {
            if (! $outcome instanceof ClaimOutcomeInstructionData) {
                throw new InvalidArgumentException(
                    'Voucher claim outcomes must use typed outcome instructions.',
                );
            }
        }

        if (! in_array($this->selection, ['claimant', 'server'], true)) {
            throw new InvalidArgumentException(
                "Unsupported Voucher claim selection [{$this->selection}].",
            );
        }

        if ($this->consumption !== 'one_of') {
            throw new InvalidArgumentException(
                "Unsupported Voucher claim consumption [{$this->consumption}].",
            );
        }

        if ($this->profile !== self::SCHEMA) {
            throw new InvalidArgumentException(
                "Unsupported Voucher claim profile [{$this->profile}].",
            );
        }

        $keys = array_map(
            static fn (ClaimOutcomeInstructionData $outcome): string => $outcome->key,
            $this->outcomes,
        );

        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException('Voucher claim outcome keys must be unique.');
        }

        if (
            $this->default_outcome !== null
            && ! in_array($this->default_outcome, $keys, true)
        ) {
            throw new InvalidArgumentException(
                'The default Voucher claim outcome must be one of the declared outcomes.',
            );
        }
    }

    public static function rules(): array
    {
        return [
            'outcomes' => ['required', 'array', 'min:1'],
            'outcomes.*' => ['required', 'array'],
            'outcomes.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'outcomes.*.pricing_profile' => ['nullable', 'string', 'min:1'],
            'outcomes.*.requirements' => ['nullable', 'array'],
            'selection' => ['nullable', 'string', 'in:claimant,server'],
            'consumption' => ['nullable', 'string', 'in:one_of'],
            'default_outcome' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'onboarding' => ['nullable', 'array'],
            'onboarding.mode' => ['nullable', 'string', 'in:never,if_required,required'],
            'onboarding.profile' => ['nullable', 'string', 'min:1'],
            'claimant' => ['nullable', 'array'],
            'claimant.mode' => ['nullable', 'string', 'in:unbound,recipient'],
            'claimant.reference' => [
                'nullable',
                'string',
                'min:1',
                'required_if:claimant.mode,recipient',
            ],
            'profile' => ['nullable', 'string', 'in:'.self::SCHEMA],
        ];
    }
}
