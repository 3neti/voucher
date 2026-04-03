<?php

use LBHurtado\Voucher\Models\MoneyIssuer;

it('has fillable properties', function () {
    $moneyIssuer = MoneyIssuer::factory()->make();

    expect($moneyIssuer->getFillable())->toBe(['code', 'name']);
});

it('can create a money issuer', function () {
    $data = [
        'code' => 'PAYPAL',
        'name' => 'PayPal',
    ];

    $moneyIssuer = MoneyIssuer::create($data);

    // Assert the record was saved in the database
    expect(MoneyIssuer::find($moneyIssuer->id))->not->toBeNull();
    $this->assertDatabaseHas('money_issuers', $data); // Laravel assertion helper

    // Verify attributes
    expect($moneyIssuer->code)->toBe('PAYPAL');
    expect($moneyIssuer->name)->toBe('PayPal');
});

it('can return a factory instance', function () {
    $factory = MoneyIssuer::newFactory();

    expect($factory)->toBeInstanceOf(\LBHurtado\Voucher\Database\Factories\MoneyIssuerFactory::class);
});
