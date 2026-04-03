<?php

use LBHurtado\Voucher\Data\CashValidationRulesData;

it('validates and serializes cash validation rules data', function () {
    $data = CashValidationRulesData::from([
        'secret' => '123456',
        'mobile' => '09171234567',
        'country' => 'PH',
        'location' => 'Makati City',
        'radius' => '1000m',
    ]);

    expect($data->secret)->toBe('123456');
    expect($data->mobile)->toBe('09171234567');
    expect($data->country)->toBe('PH');
    expect($data->location)->toBe('Makati City');
    expect($data->radius)->toBe('1000m');
});
