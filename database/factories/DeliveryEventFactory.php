<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeliveryEventFactory extends Factory
{
    public function definition(): array
    {
        return ['emitter_id' => User::factory(), 'event_id' => (string) Str::uuid(), 'delivery_id' => Delivery::factory(), 'type' => 'created', 'occurred_at' => '2026-10-29 12:00:00.000000', 'received_at' => '2026-10-29 12:00:00.000000', 'data' => ['planned_pickup' => ['label' => 'Origen de prueba', 'latitude' => 0, 'longitude' => 0], 'planned_destination' => ['label' => 'Calle 1', 'latitude' => 0.01, 'longitude' => 0]], 'fingerprint' => str_repeat('a', 64)];
    }
}
