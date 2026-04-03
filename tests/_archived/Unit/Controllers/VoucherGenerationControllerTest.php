<?php

use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Events\VouchersGenerated;

uses(RefreshDatabase::class);

it('correctly resolves the vouchers.generate route', function () {
    // Arrange: Ensure the route is registered
    Route::post('/vouchers/generate', \LBHurtado\Voucher\Http\Controllers\VoucherGenerationController::class)
        ->name('vouchers.generate');

    // Assert: Check that the route URL is generated correctly by the route name
    $routeUrl = route('vouchers.generate');

    expect($routeUrl)->toBe(url('/vouchers/generate'));
});

it('generates vouchers successfully via the named route', function () {
    Event::fake();
    //    // Arrange: Ensure the route is registered
    //    Route::post('/vouchers/generate', \LBHurtado\Voucher\Http\Controllers\VoucherGenerationController::class)
    //        ->name('vouchers.generate');

    // Example payload for VoucherInstructionsData
    $payload = [
        'cash' => [
            'amount' => 1000,
            'currency' => 'PHP',
            'validation' => [
                'secret' => '123456',
                'mobile' => '09171234567',
                'country' => 'PH',
                'location' => 'Makati City',
                'radius' => '1000m',
            ],
        ],
        'inputs' => [
            'fields' => ['email', 'mobile', 'kyc'],
        ],
        'feedback' => [
            'email' => 'test@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://webhook.site/xyz',
        ],
        'rider' => [
            'message' => 'Please redeem your voucher.',
            'url' => 'https://example.com/redeem',
        ],
        // Additional properties handled by VoucherInstructionsData
        'count' => 2, // Number of vouchers to generate
        'prefix' => 'TEST', // Prefix for voucher codes
        'mask' => '****', // Mask for voucher codes
        'ttl' => 'PT24H', // Expiry Time (TTL in ISO format)
    ];

    // Act: Send a POST request via the named route
    $response = $this->postJson(route('vouchers.generate'), $payload);
    // dd($response->json());
    // Assert: Check HTTP status and response structure
    $response->assertOk()
        ->assertJson([
            'message' => 'Vouchers successfully generated.',
        ]);

    // Extract vouchers data from response
    $responseData = $response->json('data');

    // Assert: Check that the correct number of vouchers is returned
    expect($responseData)->toHaveCount(2);

    // Assert: Validate each voucher
    collect($responseData)->each(function ($voucherData) use ($payload) {
        // Check that the metadata in the response matches the payload
        //        dd($voucherData['metadata']['instructions']);
        expect($voucherData['metadata']['instructions'])->toMatchArray($payload);

        // Fetch the voucher from the database
        $voucher = Voucher::query()->where('code', $voucherData['code'])->first();

        // Assert: Check that the voucher exists in the database
        expect($voucher)->not->toBeNull()
            ->and($voucher->metadata['instructions'])->toMatchArray($payload)
            ->and($voucher->code)->toStartWith('TEST')
            ->and($voucher->code)
            ->toMatch('/^'
                .'TEST' // Escape the prefix
                .config('vouchers.separator') // Add the separator after the prefix
                .str_replace('*', '.', '****') // Replace '*' with '.' and escape everything else
                .'$/' // Ensure the entire code matches
            );
    });

    Event::assertDispatched(VouchersGenerated::class);
});
