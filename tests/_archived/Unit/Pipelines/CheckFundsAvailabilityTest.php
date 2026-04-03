<?php

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\CheckFundsAvailability;

it('proceeds when funds are sufficient', function () {
    // Mock the external API response for sufficient funds
    Http::fake([
        config('services.funds_api.endpoint') => Http::response([
            'available' => true,
        ], 200),
    ]);

    // Mock vouchers
    $vouchers = collect([
        (object) [
            'id' => 1,
            'metadata' => [
                'instructions' => [
                    'cash' => [
                        'amount' => 100,
                        'currency' => 'USD',
                    ],
                ],
            ],
        ],
        (object) [
            'id' => 2,
            'metadata' => [
                'instructions' => [
                    'cash' => [
                        'amount' => 200,
                        'currency' => 'USD',
                    ],
                ],
            ],
        ],
    ]);

    // Execute the pipeline
    $result = app(Pipeline::class)
        ->send($vouchers)
        ->through([
            CheckFundsAvailability::class,
        ])
        ->thenReturn();

    // Assert
    expect($result)->toEqual($vouchers);
});

it('throws an exception when funds are insufficient', function () {
    // Mock the external API response for insufficient funds
    Http::fake([
        config('services.funds_api.endpoint') => Http::response([
            'available' => false,
        ], 200),
    ]);

    // Mock vouchers
    $vouchers = collect([
        (object) [
            'id' => 1,
            'metadata' => [
                'instructions' => [
                    'cash' => [
                        'amount' => 500,
                        'currency' => 'USD',
                    ],
                ],
            ],
        ],
        (object) [
            'id' => 2,
            'metadata' => [
                'instructions' => [
                    'cash' => [
                        'amount' => 700,
                        'currency' => 'USD',
                    ],
                ],
            ],
        ],
    ]);

    // Execute the pipeline and expect an exception
    app(Pipeline::class)
        ->send($vouchers)
        ->through([
            CheckFundsAvailability::class,
        ])
        ->thenReturn();
})->throws(Exception::class, 'Funds unavailable for the total amount: 1200 USD.');

it('throws an exception when the API fails', function () {
    // Mock the external API to simulate a server error
    Http::fake([
        config('services.funds_api.endpoint') => Http::response(null, 500),
    ]);

    // Mock vouchers
    $vouchers = collect([
        (object) [
            'id' => 1,
            'metadata' => [
                'instructions' => [
                    'cash' => [
                        'amount' => 200,
                        'currency' => 'USD',
                    ],
                ],
            ],
        ],
    ]);

    // Execute the pipeline and expect an exception
    app(Pipeline::class)
        ->send($vouchers)
        ->through([
            CheckFundsAvailability::class,
        ])
        ->thenReturn();
})->throws(Exception::class, 'Funds API request failed with status code: 500');

it('logs errors when funds are insufficient', function () {
    // Mock the external API for insufficient funds
    Http::fake([
        config('services.funds_api.endpoint') => Http::response([
            'available' => false,
        ], 200),
    ]);

    // Spy on Log and expect it to log an error
    Log::shouldReceive('error')
        ->once()
        ->withArgs(function ($message) {
            return str_contains($message, 'Funds unavailable');
        });

    // Mock vouchers
    $vouchers = collect([
        (object) [
            'id' => 1,
            'metadata' => [
                'instructions' => [
                    'cash' => [
                        'amount' => 1000,
                        'currency' => 'USD',
                    ],
                ],
            ],
        ],
    ]);

    // Execute the pipeline and expect an exception
    app(Pipeline::class)
        ->send($vouchers)
        ->through([
            CheckFundsAvailability::class,
        ])
        ->thenReturn();
})->throws(Exception::class);
