<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryRevisionFactory extends Factory
{
    public function definition(): array
    {
        return ['delivery_id' => Delivery::factory(), 'event_id' => DeliveryEvent::factory(), 'snapshot' => [], 'reason' => 'event_received'];
    }
}
