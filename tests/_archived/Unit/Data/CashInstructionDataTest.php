<?php

use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\CashValidationRulesData;

it('validates and serializes cash instruction data', function () {
    $data = CashInstructionData::from([
        'amount' => 1000,
        'currency' => 'PHP',
        'validation' => [
            'secret' => '123456',
            'mobile' => '09171234567',
            'country' => 'PH',
            'location' => 'Makati City',
            'radius' => '1000m',
        ],
    ]);

    expect($data->amount)->toBe(1000.0);
    expect($data->currency)->toBe('PHP');
    expect($data->validation)->toBeInstanceOf(CashValidationRulesData::class);
    expect($data->validation->mobile)->toBe('09171234567');
});
