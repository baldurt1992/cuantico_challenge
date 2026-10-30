<?php

use App\Models\Courier;
use App\Models\Merchant;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Support\Str;

function deliveryScenario(string $role = 'operator'): array
{
    $merchant = Merchant::factory()->create();
    $user = User::factory()->create(['role' => $role]);
    $user->merchants()->attach($merchant);
    $courier = Courier::factory()->create();
    $courier->merchants()->attach($merchant);
    $tariff = Tariff::factory()->for($merchant)->create();

    return compact('merchant', 'user', 'courier', 'tariff');
}

function deliveryEvents(int $merchantId, int $courierId = 1, ?string $deliveryId = null): array
{
    $deliveryId ??= (string) Str::uuid();
    $types = ['created', 'assigned', 'picked_up', 'in_transit', 'delivered'];
    $data = [
        ['planned_pickup' => ['label' => 'Origen de prueba', 'latitude' => 0, 'longitude' => 0], 'planned_destination' => ['label' => 'Calle 80, portería', 'latitude' => 0.0111, 'longitude' => 0]],
        ['courier_id' => $courierId],
        ['latitude' => 0, 'longitude' => 0],
        [],
        ['latitude' => 0.0111, 'longitude' => 0],
    ];

    return array_map(fn (int $i): array => [
        'event_id' => (string) Str::uuid(),
        'delivery_id' => $deliveryId,
        'merchant_id' => $merchantId,
        'type' => $types[$i],
        'occurred_at' => '2026-10-29T20:0'.$i.':00-05:00',
        'data' => $data[$i],
    ], range(0, 4));
}
