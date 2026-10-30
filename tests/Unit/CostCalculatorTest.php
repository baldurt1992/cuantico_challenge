<?php

use App\Services\CostCalculator;
use App\Services\DeliveryProjector;

it('calculates the documented night example with an exact additive breakdown', function (): void {
    $projection = (new DeliveryProjector)->project(deliveryEvents(1));
    $tariff = ['id' => 1, 'base_minor' => 500000, 'per_km_minor' => 100000, 'timezone' => 'America/Bogota'];

    $cost = (new CostCalculator)->calculate($projection, $tariff);

    expect($cost)->toMatchArray(['distance_meters' => 1234, 'distance_minor' => 123400, 'night_minor' => 93510, 'total_minor' => 716910, 'night' => true]);
});

it('uses local completion time at exact night boundaries', function (string $time, bool $night, int $expected): void {
    $events = deliveryEvents(1);
    foreach ($events as &$event) {
        $event['occurred_at'] = '2026-10-29T'.$time.'-05:00';
    }
    $projection = (new DeliveryProjector)->project($events);

    $cost = (new CostCalculator)->calculate($projection, ['id' => 1, 'base_minor' => 500000, 'per_km_minor' => 0, 'timezone' => 'America/Bogota']);

    expect($cost)->toMatchArray(['night' => $night, 'total_minor' => $expected]);
})->with([
    'before night' => ['19:59:59', false, 500000],
    'night starts' => ['20:00:00', true, 575000],
    'before morning' => ['05:59:59', true, 575000],
    'morning starts' => ['06:00:00', false, 500000],
]);

it('charges only forty percent on failure even after pickup at night', function (): void {
    $events = array_slice(deliveryEvents(1), 0, 4);
    $events[] = ['type' => 'failed', 'occurred_at' => '2026-10-29T23:00:00-05:00', 'data' => []];

    $cost = (new CostCalculator)->calculate((new DeliveryProjector)->project($events), ['id' => 1, 'base_minor' => 500000, 'per_km_minor' => 100000, 'timezone' => 'America/Bogota']);

    expect($cost)->toMatchArray(['total_minor' => 200000, 'distance_minor' => 0, 'night_minor' => 0, 'night' => false]);
});

it('leaves incomplete and contradictory costs unknown', function (): void {
    $projection = (new DeliveryProjector)->project([deliveryEvents(1)[4]]);

    expect((new CostCalculator)->calculate($projection, []))->toBeNull();
});

it('handles identical points and an independently known equatorial arc', function (): void {
    $calculator = new CostCalculator;

    expect($calculator->distanceMeters(['latitude' => 0, 'longitude' => 0], ['latitude' => 0, 'longitude' => 0]))->toBe(0);
    expect($calculator->distanceMeters(['latitude' => 0, 'longitude' => 0], ['latitude' => 1, 'longitude' => 0]))->toBe(111195);
});

it('rejects unsupported monetary inputs before arithmetic', function (int $base): void {
    $projection = (new DeliveryProjector)->project(deliveryEvents(1));

    expect(fn () => (new CostCalculator)->calculate($projection, ['id' => 1, 'base_minor' => $base, 'per_km_minor' => 0, 'timezone' => 'UTC']))->toThrow(InvalidArgumentException::class);
})->with([-1, 1000000001]);
