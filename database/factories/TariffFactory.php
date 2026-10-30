<?php

namespace Database\Factories;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TariffFactory extends Factory
{
    public function definition(): array
    {
        return ['merchant_id' => Merchant::factory(), 'effective_from' => '2020-01-01 00:00:00.000000', 'base_minor' => 500000, 'per_km_minor' => 100000, 'currency' => 'COP', 'timezone' => 'America/Bogota'];
    }
}
