<?php

use Carbon\CarbonInterval;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;

it('validates and serializes voucher instructions data', function () {
    $data = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 1500,
            'currency' => 'USD',
            'validation' => [
                'secret' => '654321',
                'mobile' => '09179876543',
                'country' => 'US',
                'location' => 'New York',
                'radius' => '5000m',
            ],
        ],
        'inputs' => [
            'fields' => ['email', 'mobile', 'reference_code'],
        ],
        'feedback' => [
            'email' => 'support@company.com',
            'mobile' => '09179876543',
            'webhook' => 'https://company.com/webhook',
        ],
        'rider' => [
            'message' => 'Welcome to our company!',
            'url' => 'https://company.com/rider-url',
        ],
        'count' => 3, // New field for count
        'prefix' => 'PROMO', // New field for prefix
        'mask' => '******', // New field for mask
        //        'ttl' => CarbonInterval::hours(24), // New field for ttl
    ]);

    // Validate nested properties
    expect($data->cash->amount)->toBe(1500.0);
    expect($data->cash->currency)->toBe('USD');
    expect($data->cash->validation->country)->toBe('US');
    expect($data->inputs->fields)->toContain(VoucherInputField::EMAIL);
    expect($data->feedback->email)->toBe('support@company.com');
    expect($data->rider->message)->toBe('Welcome to our company!');

    // Validate new properties
    expect($data->count)->toBe(3);
    expect($data->prefix)->toBe('PROMO');
    expect($data->mask)->toBe('******');
    //    expect($data->ttl)->toEqual(CarbonInterval::hours(24));
});

it('validates and constructs instructions from array', function () {
    $data = VoucherInstructionsData::createFromAttribs([
        'cash' => ['amount' => 10, 'currency' => 'PHP'],
        //        'inputs' => ['fields' => []],
        'count' => 5,
    ]);

    expect($data)
        ->cash->amount->toBe(10.0)
        ->count->toBe(5)
        ->inputs->fields->toBeArray();
});
