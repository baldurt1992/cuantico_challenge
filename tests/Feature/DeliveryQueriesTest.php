<?php

use App\Models\Delivery;
use App\Services\EventIngestor;

it('aggregates finalized costs and scopes every search to the authorized merchant', function (): void {
    $scenario = deliveryScenario();
    $other = deliveryScenario();
    $ingestor = app(EventIngestor::class);
    foreach (deliveryEvents($scenario['merchant']->id, $scenario['courier']->id) as $event) {
        $ingestor->ingest($scenario['user'], $event);
    }
    Delivery::factory()->for($other['merchant'])->create(['delivery_address' => 'Calle 80 ajena', 'cost_minor' => 999999]);
    $url = '/api/v1/merchants/'.$scenario['merchant']->id.'/operations?start_date=2026-10-29&end_date=2026-10-30&text=Calle';

    $this->actingAs($scenario['user'])->getJson($url)->assertOk()->assertJsonPath('totals.total_minor', 716910)->assertJsonPath('totals.deliveries', 1)->assertJsonPath('deliveries.total', 1);
    $this->getJson('/api/v1/merchants/'.$other['merchant']->id.'/operations?period=last_week')->assertNotFound();
});

it('treats SQL and LIKE wildcards as literal search input', function (string $text): void {
    $scenario = deliveryScenario();
    Delivery::factory()->for($scenario['merchant'])->create(['delivery_address' => 'Calle 1']);
    $url = '/api/v1/merchants/'.$scenario['merchant']->id.'/operations?period=last_week&text='.urlencode($text);

    $this->actingAs($scenario['user'])->getJson($url)->assertOk()->assertJsonPath('deliveries.total', 0);
})->with(["%' OR 1=1 --", '%%', '__', '\\%']);

it('reports the current cohort status rather than silently using the completion period', function (): void {
    $scenario = deliveryScenario();
    Delivery::factory()->for($scenario['merchant'])->create(['courier_id' => $scenario['courier']->id, 'created_occurred_at' => '2026-10-28 23:00:00.000000', 'completed_at' => '2026-10-30 02:00:00.000000', 'status' => 'delivered', 'quality' => 'consistent', 'cost_minor' => 500000]);
    $url = '/api/v1/merchants/'.$scenario['merchant']->id.'/operations?start_date=2026-10-28&end_date=2026-10-29&courier_id='.$scenario['courier']->id;

    $this->actingAs($scenario['user'])->getJson($url)->assertOk()->assertJsonPath('totals.total_minor', 0)->assertJsonPath('courier_counts.counts.delivered', 1)->assertJsonPath('courier_counts.period.criterion', 'creación');
});

it('resolves the previous calendar week using the merchant timezone', function (): void {
    $this->travelTo(new DateTimeImmutable('2026-11-02T02:00:00Z'));
    $scenario = deliveryScenario();

    $this->actingAs($scenario['user'])->getJson('/api/v1/merchants/'.$scenario['merchant']->id.'/operations?period=last_week')->assertOk()
        ->assertJsonPath('totals.period.start_date', '2026-10-19')->assertJsonPath('totals.period.end_date_exclusive', '2026-10-26');
});

it('does not expose another merchants audit history', function (): void {
    $scenario = deliveryScenario();
    $other = deliveryScenario();
    $delivery = Delivery::factory()->for($other['merchant'])->create();

    $this->actingAs($scenario['user'])->getJson('/api/v1/merchants/'.$scenario['merchant']->id.'/deliveries/'.$delivery->id)->assertNotFound();
});

it('includes the start and excludes the end of a completion window', function (): void {
    $scenario = deliveryScenario();
    foreach (['2026-10-29 05:00:00.000000', '2026-10-30 05:00:00.000000'] as $date) {
        Delivery::factory()->for($scenario['merchant'])->create(['completed_at' => $date, 'status' => 'failed', 'quality' => 'consistent', 'cost_minor' => 200000]);
    }

    $this->actingAs($scenario['user'])->getJson('/api/v1/merchants/'.$scenario['merchant']->id.'/operations?start_date=2026-10-29&end_date=2026-10-30')
        ->assertOk()->assertJsonPath('totals.total_minor', 200000)->assertJsonPath('totals.failed', 1);
});

it('finds equivalent destination text without accents case or repeated spaces across merchant boundaries', function (string $text): void {
    $scenario = deliveryScenario('reader');
    $other = deliveryScenario();
    Delivery::factory()->for($scenario['merchant'])->create(['delivery_address' => 'Parque Simón Bolívar · Parque metropolitano, Bogotá']);
    Delivery::factory()->for($scenario['merchant'])->create(['delivery_address' => 'Punto ajustado cerca de Parque Simón Bolívar · Bogotá']);
    Delivery::factory()->for($other['merchant'])->create(['delivery_address' => 'Parque Simón Bolívar · Bogotá']);

    $this->actingAs($scenario['user'])->getJson('/api/v1/merchants/'.$scenario['merchant']->id.'/operations?period=last_week&text='.urlencode($text))
        ->assertOk()->assertJsonPath('deliveries.total', 2)->assertJsonCount(2, 'deliveries.data');
})->with(['parque simón bolivar', 'parque simon bolivar', 'PARQUE SIMÓN BOLÍVAR', '  parque   simon   bolivar  ', "parque simo\u{0301}n boli\u{0301}var"]);

it('also searches the planned origin with the same normalization', function (): void {
    $scenario = deliveryScenario('reader');
    Delivery::factory()->for($scenario['merchant'])->create(['delivery_address' => 'Destino distinto', 'planned_pickup' => ['label' => 'Parque Simón Bolívar', 'latitude' => 4.658056, 'longitude' => -74.093889]]);

    $this->actingAs($scenario['user'])->getJson('/api/v1/merchants/'.$scenario['merchant']->id.'/operations?period=last_week&text=parque+simon+bolivar')
        ->assertOk()->assertJsonPath('deliveries.total', 1);
});

it('normalizes names of merchants and couriers when searching', function (string $text): void {
    $scenario = deliveryScenario('reader');
    $scenario['merchant']->update(['name' => 'Casa Botánica']);
    $scenario['courier']->update(['name' => 'Lucía Gómez']);
    Delivery::factory()->for($scenario['merchant'])->create(['delivery_address' => 'Destino distinto', 'courier_id' => $scenario['courier']->id]);

    $this->actingAs($scenario['user'])->getJson('/api/v1/merchants/'.$scenario['merchant']->id.'/operations?period=last_week&text='.urlencode($text))
        ->assertOk()->assertJsonPath('deliveries.total', 1);
})->with(['botanica', 'lucia gomez']);

it('keeps percent underscore and backslash literal in normalized searches', function (string $text): void {
    $scenario = deliveryScenario('reader');
    Delivery::factory()->for($scenario['merchant'])->create(['delivery_address' => 'Local 10% A_ \\%']);
    Delivery::factory()->for($scenario['merchant'])->create(['delivery_address' => 'Local 100 A1 otra dirección']);

    $this->actingAs($scenario['user'])->getJson('/api/v1/merchants/'.$scenario['merchant']->id.'/operations?period=last_week&text='.urlencode($text))
        ->assertOk()->assertJsonPath('deliveries.total', 1);
})->with(['10%', 'A_', '\\%']);
