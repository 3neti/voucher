<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LBHurtado\Voucher\Data\ModelData;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher as VoucherModel;

uses()->group('voucher-data');

it('correctly maps a full Voucher model to VoucherData DTO', function () {
    // 1. Create dummy owner and redeemer (any Eloquent model with name/email/mobile)
    $owner = new class extends Model
    {
        public $name = 'Owner Name';

        public $email = 'owner@example.com';

        public $mobile = '09170000000';
    };
    $redeemer = new class extends Model
    {
        public $name = 'Redeemer Name';

        public $email = 'redeemer@example.com';

        public $mobile = '09171111111';
    };

    // 2. Build an instructions DTO and embed its array form into metadata
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

    // 3. Create a fake VoucherModel and set all its properties

    $voucher = new VoucherModel;
    $voucher->code = 'ABC-12345';
    $voucher->owner = $owner;
    $voucher->starts_at = Carbon::parse('2025-01-01 10:00:00');
    $voucher->expires_at = Carbon::parse('2025-01-03 10:00:00');
    $voucher->redeemed_at = Carbon::parse('2025-01-02 12:00:00');
    $voucher->processed_on = Carbon::parse('2025-01-04 09:00:00');
    // processed is derived
    $voucher->processed = true;
    // metadata instructions comes from model accessor
    $voucher->metadata = ['instructions' => $instructions->toArray()];
    //    $voucher->redeemer     = $redeemer;

    // 4. Turn it into your DTO
    $dto = VoucherData::fromModel($voucher);

    // 5. Assertions
    expect($dto)
        ->code->toBe('ABC-12345')
        ->owner->toBeInstanceOf(ModelData::class)
        ->owner->name->toBe('Owner Name')
        ->owner->email->toBe('owner@example.com')
        ->owner->mobile->toBe('09170000000')
        ->starts_at->toBeInstanceOf(Carbon::class)
        ->starts_at->toDateTimeString()->toBe('2025-01-01 10:00:00')
        ->expires_at->toDateTimeString()->toBe('2025-01-03 10:00:00')
        ->redeemed_at->toDateTimeString()->toBe('2025-01-02 12:00:00')
//        ->processed_on->toDateTimeString()->toBe('2025-01-04 09:00:00')
        ->processed->toBeTrue()
        ->instructions->toBeInstanceOf(VoucherInstructionsData::class)
        ->instructions->cash->amount->toBe(500.0)
        ->instructions->count->toBe(2)
        ->instructions->prefix->toBe('PRE')
        ->instructions->mask->toBe('******');
    //        ->instructions->ttl->toEqual(Carbon::parse('2025-01-03 10:00:00')
    //            ->diffAsCarbonInterval(Carbon::parse('2025-01-01 10:00:00')))

    //        ->redeemer->toBeInstanceOf(ModelData::class)
    //        ->redeemer->name->toBe('Redeemer Name')
    //        ->redeemer->email->toBe('redeemer@example.com')
    //        ->redeemer->mobile->toBe('09171111111')
});
