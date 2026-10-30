<?php

use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\DeliveryRevision;

it('persists the planned route and derives the displayed destination from it', function (): void {
    $scenario = deliveryScenario();
    $created = deliveryEvents($scenario['merchant']->id, $scenario['courier']->id)[0];
    $this->actingAs($scenario['user'])->postJson('/api/v1/events', $created)
        ->assertCreated()->assertJsonPath('delivery.delivery_address', 'Calle 80, portería')
        ->assertJsonPath('delivery.planned_pickup.label', 'Origen de prueba')
        ->assertJsonPath('delivery.planned_destination.label', 'Calle 80, portería')
        ->assertJsonPath('delivery.cost_minor', null);

    $delivery = Delivery::findOrFail($created['delivery_id']);
    expect($delivery->planned_pickup['label'])->toBe('Origen de prueba');
    expect(DeliveryEvent::firstOrFail()->data)->toHaveKeys(['planned_pickup', 'planned_destination'])->not->toHaveKey('delivery_address');
    expect(DeliveryRevision::firstOrFail()->snapshot['planned_destination']['label'])->toBe('Calle 80, portería');
    $this->getJson('/api/v1/merchants/'.$scenario['merchant']->id.'/deliveries/'.$delivery->id)
        ->assertOk()->assertJsonPath('delivery.planned_pickup.label', 'Origen de prueba');
});

it('calculates from actual event coordinates even when they differ from the planned route', function (): void {
    $scenario = deliveryScenario();
    $events = deliveryEvents($scenario['merchant']->id, $scenario['courier']->id);
    $events[0]['data']['planned_pickup'] = ['label' => 'Origen previsto', 'latitude' => 40, 'longitude' => -74];
    $events[0]['data']['planned_destination'] = ['label' => 'Destino previsto', 'latitude' => 45, 'longitude' => -73];
    $this->actingAs($scenario['user']);
    foreach ([4, 2, 3, 1, 0] as $index) {
        $this->postJson('/api/v1/events', $events[$index])->assertCreated();
    }

    $delivery = Delivery::findOrFail($events[0]['delivery_id']);
    expect($delivery->quality)->toBe('consistent');
    expect($delivery->cost_minor)->toBe(716910);
    expect($delivery->calculation['pickup']['latitude'])->toEqual(0);
    expect($delivery->calculation['destination']['latitude'])->toEqual(0.0111);
    expect($delivery->planned_pickup['latitude'])->toEqual(40);
    expect($delivery->planned_destination['label'])->toBe('Destino previsto');
    expect($delivery->delivery_address)->toBe('Destino previsto');
});

it('normalizes retries of the route and rejects a changed planned point with the same event id', function (): void {
    $scenario = deliveryScenario();
    $event = deliveryEvents($scenario['merchant']->id, $scenario['courier']->id)[0];
    $this->actingAs($scenario['user'])->postJson('/api/v1/events', $event)->assertCreated();
    $event['data']['planned_pickup'] = ['longitude' => '0.0', 'latitude' => '0', 'label' => 'Origen de prueba'];
    $this->postJson('/api/v1/events', $event)->assertOk()->assertJsonPath('duplicate', true);
    $event['data']['planned_pickup']['latitude'] = 1;
    $this->postJson('/api/v1/events', $event)->assertConflict();
    $this->assertDatabaseCount('delivery_events', 1);
    expect(Delivery::firstOrFail()->planned_pickup['latitude'])->toEqual(0);
});

it('rejects incomplete or untrusted planned locations before persisting a delivery', function (string $field, mixed $value): void {
    $scenario = deliveryScenario();
    $event = deliveryEvents($scenario['merchant']->id, $scenario['courier']->id)[0];
    data_set($event, $field, $value);

    $this->actingAs($scenario['user'])->postJson('/api/v1/events', $event)->assertUnprocessable();

    $this->assertDatabaseCount('deliveries', 0);
    $this->assertDatabaseCount('delivery_events', 0);
})->with([
    'address without planned points' => ['data', ['delivery_address' => 'Dirección desconectada']],
    'missing origin' => ['data.planned_pickup', null],
    'empty origin' => ['data.planned_pickup', []],
    'missing destination' => ['data.planned_destination', null],
    'empty label' => ['data.planned_destination.label', ''],
    'invalid latitude' => ['data.planned_pickup.latitude', 91],
    'invalid longitude' => ['data.planned_destination.longitude', -181],
    'unknown nested key' => ['data.planned_pickup.merchant_id', 999],
    'independent destination address' => ['data.delivery_address', 'Otra dirección'],
]);
