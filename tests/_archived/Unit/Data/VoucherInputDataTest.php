<?php

use FrittenKeeZ\Vouchers\Facades\Vouchers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\ModelInput\Data\InputData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use Spatie\LaravelData\DataCollection;

uses(RefreshDatabase::class);

it('correctly maps voucher inputs to InputData collection', function () {
    $instructions = VoucherInstructionsData::from([
        'cash' => [
            'amount' => 500,
            'currency' => 'PHP',
            'validation' => [
                'secret' => 'XYZ123',
                'mobile' => '09172222222',
                'country' => 'PH',
                'location' => 'Manila',
                'radius' => '100m',
            ],
        ],
        'inputs' => ['fields' => ['email', 'mobile']],
        'feedback' => [
            'email' => 'support@example.com',
            'mobile' => '09173333333',
            'webhook' => 'https://example.com/webhook',
        ],
        'rider' => [
            'message' => 'Enjoy your voucher!',
            'url' => 'https://example.com/rider',
        ],
        'count' => 2,
        'prefix' => 'PRE',
        'mask' => '******',
        'ttl' => 'PT48H',
    ]);

    $voucher = Vouchers::create();
    $voucher->mobile = '09171234567';
    $voucher->name = 'John Doe';
    $voucher->metadata = ['instructions' => $instructions->toArray()];
    $voucher->save();
    expect($voucher->getData()->inputs)->toBeInstanceOf(DataCollection::class);
    expect($voucher->getData()->inputs->first())->toBeInstanceOf(InputData::class);
});
