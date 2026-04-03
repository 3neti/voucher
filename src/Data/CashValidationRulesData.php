<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use Spatie\LaravelData\Data;

class CashValidationRulesData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public ?string $secret,
        public ?string $mobile,
        public ?string $payable, // vendor alias text - who can redeem (e.g., "BB", "TESTSHOP")
        public ?string $country,
        public ?string $location,
        public ?string $radius, // future: consider DistanceValueObject
        public ?MobileVerificationConfigData $mobile_verification = null,
    ) {
        $this->applyRulesAndDefaults();
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'secret' => [
                ['required', 'string', 'min:4', 'max:255'],
                config('instructions.cash.validation_rules.secret'),
            ],
            'mobile' => [
                ['required', (new \Propaganistas\LaravelPhone\Rules\Phone)->country('PH')->type('mobile')],
                config('instructions.cash.validation_rules.mobile'),
            ],
            'payable' => [
                ['nullable', 'string', 'max:20'],
                null,
            ],
            'country' => [
                ['required', 'string', 'size:2'],
                config('instructions.cash.validation_rules.country'),
            ], // ISO 3166-1 alpha-2
            'location' => [
                ['required', 'string', 'max:255'],
                config('instructions.cash.validation_rules.location'),
            ],
            'radius' => [
                ['required', 'string', 'regex:/^\d+(m|km)$/'], // e.g. "1000m", "2km"
                config('instructions.cash.validation_rules.radius'),
            ],
        ];
    }
}
