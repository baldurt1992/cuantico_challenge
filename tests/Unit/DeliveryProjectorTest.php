<?php

use App\Services\DeliveryProjector;

it('reconstructs the same terminal independently of arrival order', function (): void {
    $events = deliveryEvents(1);
    $projector = new DeliveryProjector;
    $expected = $projector->project($events);

    foreach ([[4, 2, 0, 3, 1], [1, 4, 3, 0, 2], [4, 3, 2, 1, 0]] as $order) {
        expect($projector->project(array_map(fn (int $i): array => $events[$i], $order)))->toBe($expected);
    }
    expect($expected)->toMatchArray(['status' => 'delivered', 'quality' => 'consistent', 'issues' => []]);
});

it('keeps missing predecessors pending without rejecting advanced events', function (): void {
    $events = deliveryEvents(1);

    $projection = (new DeliveryProjector)->project([$events[4]]);

    expect($projection)->toMatchArray(['status' => 'delivered', 'quality' => 'pending', 'completed_at' => null]);
    expect($projection['issues'])->toContain('missing_created', 'missing_picked_up');
});

it('allows failure from every nonterminal prefix', function (int $length): void {
    $events = array_slice(deliveryEvents(1), 0, $length);
    $events[] = ['event_id' => 'failure', 'type' => 'failed', 'occurred_at' => '2026-10-29T22:00:00-05:00', 'data' => []];

    $projection = (new DeliveryProjector)->project($events);

    expect($projection)->toMatchArray(['status' => 'failed', 'quality' => 'consistent', 'issues' => []]);
})->with([1, 2, 3, 4]);

it('flags conflicting terminals and does not confirm a costable state', function (): void {
    $events = deliveryEvents(1);
    $events[] = ['event_id' => 'failure', 'type' => 'failed', 'occurred_at' => '2026-10-29T23:00:00-05:00', 'data' => []];

    $projection = (new DeliveryProjector)->project($events);

    expect($projection)->toMatchArray(['status' => null, 'quality' => 'conflict', 'completed_at' => null]);
    expect($projection['issues'])->toContain('contradictory_terminals');
});

it('orders timestamp ties by lifecycle without depending on UUID', function (): void {
    $events = deliveryEvents(1);
    foreach ($events as &$event) {
        $event['occurred_at'] = '2026-10-29T22:00:00-05:00';
    }

    $projection = (new DeliveryProjector)->project(array_reverse($events));

    expect($projection)->toMatchArray(['status' => 'delivered', 'quality' => 'consistent']);
});

it('flags repeated types with a new identifier', function (): void {
    $events = deliveryEvents(1);
    $copy = $events[2];
    $copy['event_id'] = 'another-id';
    $events[] = $copy;

    expect((new DeliveryProjector)->project($events)['issues'])->toContain('repeated_status');
});

it('flags an impossible occurrence sequence', function (): void {
    $events = deliveryEvents(1);
    $events[2]['occurred_at'] = '2026-10-29T19:00:00-05:00';

    expect((new DeliveryProjector)->project($events)['quality'])->toBe('conflict');
});
