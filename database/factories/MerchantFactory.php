<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MerchantFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->company(), 'notes' => '', 'timezone' => 'America/Bogota'];
    }
}
