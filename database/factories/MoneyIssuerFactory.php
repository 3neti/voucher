<?php

namespace LBHurtado\Voucher\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LBHurtado\Voucher\Models\MoneyIssuer;

class MoneyIssuerFactory extends Factory
{
    protected $model = MoneyIssuer::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->randomNumber(8),
            'name' => $this->faker->name,
        ];
    }
}
