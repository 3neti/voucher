<?php

use FrittenKeeZ\Vouchers\Facades\Vouchers;
use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Events\VouchersGenerated;
use LBHurtado\Voucher\Listeners\HandleGeneratedVouchers;
use LBHurtado\Voucher\Tests\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock the external API response for sufficient funds
    Http::fake([
        config('services.funds_api.endpoint') => Http::response([
            'available' => true,
        ], 200),
    ]);
    $user = User::factory()->create();
    $user->depositFloat(100000);
    $this->actingAs($user);
});

dataset('voucher_instructions', function () {
    return [
        [fn () => VoucherInstructionsData::from([
            'cash' => [
                'amount' => 2000,
                'currency' => 'USD',
                'validation' => [
                    'secret' => '123456',
                    'mobile' => '09179876543',
                    'country' => 'US',
                    'location' => 'New York',
                    'radius' => '5000m',
                ],
            ],
            'inputs' => [
                'fields' => [
                    VoucherInputField::EMAIL->value, // Use enum values directly
                    VoucherInputField::MOBILE->value,
                ],
            ],
            'feedback' => [
                'email' => 'support@company.com',
                'mobile' => '09179876543',
                'webhook' => 'https://company.com/webhook',
            ],
            'rider' => [
                'message' => 'Welcome!',
                'url' => 'https://company.com/welcome',
            ],
            'count' => 2,
            'prefix' => 'TEST',
            'mask' => '******',
            'ttl' => 'PT24H', // ISO8601 duration string (24 hours)
        ])],
    ];
});

it('handles generated vouchers and creates associated cash records', function (VoucherInstructionsData $instructions) {
    $vouchers = Vouchers::withPrefix($instructions->prefix)
        ->withMask($instructions->mask)
        ->withMetadata([
            'instructions' => $instructions->toArray(),
        ])
        ->withExpireTimeIn($instructions->ttl)
        ->withOwner(auth()->user())
        ->create($instructions->count);

    $collection = collect($vouchers instanceof Voucher ? [$vouchers] : $vouchers);

    expect($collection->first()->owner)->toBeInstanceOf(Authenticatable::class);
    // Dispatch the VouchersGenerated event
    $event = new VouchersGenerated($collection);

    // Handle the event with the listener
    $listener = new HandleGeneratedVouchers;
    $listener->handle($event);
    expect($collection->first()->owner)->toBeInstanceOf(Authenticatable::class);
    // Assert: Check that Cash records were created
    expect(Cash::count())->toBe(2);

    foreach ($vouchers as $voucher) {
        $cash = $voucher->getEntities(Cash::class)->first();

        // Ensure the Cash record exists and contains the correct data
        expect($cash)->not->toBeNull()
            ->and($cash->amount->getAmount()->toFloat())->toBe($instructions->cash->amount)
            ->and($cash->currency)->toBe($instructions->cash->currency);
    }

    // Assert: Ensure that vouchers are marked as processed
    foreach ($vouchers as $voucher) {
        //        $voucher->refresh();
        expect($voucher->processed)->toBeTrue();
    }
})->with('voucher_instructions');

it('does not process vouchers that are already marked as processed', function (VoucherInstructionsData $instructions) {
    // Act: Create a voucher marked as already processed
    $voucher = Vouchers::withPrefix($instructions->prefix)
        ->withMask($instructions->mask)
        ->withMetadata([
            'instructions' => $instructions->toArray(),
        ])
        ->withExpireTimeIn($instructions->ttl)
        ->withOwner(auth()->user())
        ->create(1)
        ->first();

    $voucher->processed = true;
    $voucher->save();

    // Dispatch the VouchersGenerated event
    $event = new VouchersGenerated(collect([$voucher]));

    // Handle the event with the listener
    $listener = new HandleGeneratedVouchers;
    $listener->handle($event);

    // Assert: Ensure no Cash record was created
    expect(Cash::count())->toBe(0);
})->with('voucher_instructions');

it('assigns the authenticated user as the owner of the vouchers', function (VoucherInstructionsData $instructions) {
    $generateVouchersAction = app(LBHurtado\Voucher\Actions\GenerateVouchers::class);
    $vouchers = $generateVouchersAction->handle($instructions);
    // Assert: Confirm Vouchers have the correct owner
    foreach ($vouchers as $voucher) {
        expect($voucher->owner->is(auth()->user()))->toBeTrue();
    }
})->with('voucher_instructions');
