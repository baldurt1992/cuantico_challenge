<?php

namespace Database\Factories;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeliveryFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => (string) Str::uuid(), 'merchant_id' => Merchant::factory(), 'quality' => 'pending'];
    }
}
