<?php

use App\Models\Delivery;
use App\Models\DeliveryRevision;
use App\Models\Tariff;
use Illuminate\Support\Str;

it('accepts events out of order and does not apply a retry twice', function (): void {
    $scenario = deliveryScenario();
    $events = deliveryEvents($scenario['merchant']->id, $scenario['courier']->id);
    $this->actingAs($scenario['user']);
    foreach ([4, 2, 0, 3, 1] as $i) {
        $this->postJson('/api/v1/events', $events[$i])->assertCreated();
    }
    $count = DeliveryRevision::count();

    $this->postJson('/api/v1/events', $events[4])->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('delivery.cost_minor', 716910);

    $this->assertDatabaseCount('delivery_events', 5);
    $this->assertDatabaseCount('delivery_revisions', $count);
    $this->assertDatabaseHas('deliveries', ['id' => $events[0]['delivery_id'], 'quality' => 'consistent', 'cost_minor' => 716910]);
});

it('treats equivalent UTC dates and numeric coordinates as the same retry', function (): void {
    $scenario = deliveryScenario();
    $event = deliveryEvents($scenario['merchant']->id, $scenario['courier']->id)[2];
    $this->actingAs($scenario['user'])->postJson('/api/v1/events', $event)->assertCreated();
    $event['occurred_at'] = '2026-10-30T01:02:00Z';
    $event['data'] = ['longitude' => 0.0, 'latitude' => '0'];

    $this->postJson('/api/v1/events', $event)->assertOk()->assertJsonPath('duplicate', true);

    $this->assertDatabaseCount('delivery_events', 1);
});

it('rejects reused identifiers with changed business content without changing the event', function (): void {
    $scenario = deliveryScenario();
    $event = deliveryEvents($scenario['merchant']->id, $scenario['courier']->id)[0];
    $this->actingAs($scenario['user'])->postJson('/api/v1/events', $event)->assertCreated();
    $event['data']['planned_destination']['label'] = 'Otra dirección';

    $this->postJson('/api/v1/events', $event)->assertConflict();

    $this->assertDatabaseCount('delivery_events', 1);
    $this->assertDatabaseHas('deliveries', ['delivery_address' => 'Calle 80, portería']);
});

it('denies readers and other merchants before persisting events', function (): void {
    $reader = deliveryScenario('reader');
    $other = deliveryScenario();
    $event = deliveryEvents($other['merchant']->id, $other['courier']->id)[0];

    $this->actingAs($reader['user'])->postJson('/api/v1/events', $event)->assertNotFound();
    $event = deliveryEvents($reader['merchant']->id, $reader['courier']->id)[0];
    $this->postJson('/api/v1/events', $event)->assertForbidden();

    $this->assertDatabaseCount('delivery_events', 0);
});

it('uses creation tariff despite late receipt after a tariff change', function (): void {
    $scenario = deliveryScenario();
    Tariff::factory()->for($scenario['merchant'])->create(['effective_from' => '2026-10-30 01:01:00.000000', 'base_minor' => 900000]);
    $events = deliveryEvents($scenario['merchant']->id, $scenario['courier']->id);
    $this->actingAs($scenario['user']);
    foreach (array_reverse($events) as $event) {
        $this->postJson('/api/v1/events', $event)->assertCreated();
    }

    $this->assertDatabaseHas('deliveries', ['id' => $events[0]['delivery_id'], 'cost_minor' => 716910]);
    expect(Delivery::first()->calculation['tariff_id'])->toBe($scenario['tariff']->id);
});

it('preserves the previous calculation when a late terminal causes conflict', function (): void {
    $scenario = deliveryScenario();
    $events = deliveryEvents($scenario['merchant']->id, $scenario['courier']->id);
    $this->actingAs($scenario['user']);
    foreach ($events as $event) {
        $this->postJson('/api/v1/events', $event)->assertCreated();
    }
    $failed = [...$events[4], 'event_id' => (string) Str::uuid(), 'type' => 'failed', 'data' => []];

    $this->postJson('/api/v1/events', $failed)->assertCreated()->assertJsonPath('delivery.quality', 'conflict')->assertJsonPath('delivery.cost_minor', null);

    expect(DeliveryRevision::where('delivery_id', $events[0]['delivery_id'])->get()->contains(fn ($revision): bool => ($revision->snapshot['cost_minor'] ?? null) === 716910))->toBeTrue();
});

it('rejects malformed contracts without creating a delivery', function (string $field, mixed $value): void {
    $scenario = deliveryScenario();
    $event = deliveryEvents($scenario['merchant']->id, $scenario['courier']->id)[0];
    $event[$field] = $value;

    $this->actingAs($scenario['user'])->postJson('/api/v1/events', $event)->assertUnprocessable();

    $this->assertDatabaseCount('deliveries', 0);
})->with([
    'unknown top-level input' => ['emitter_id', 123],
    'timestamp lacks timezone' => ['occurred_at', '2026-10-29T20:00:00'],
    'invalid identifier' => ['event_id', 'random'],
    'unknown event type' => ['type', 'refund'],
    'unknown nested key' => ['data', ['delivery_address' => 'Calle 1', 'sql' => 'anything']],
]);
