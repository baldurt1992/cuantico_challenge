<?php

namespace App\Services;

use App\DeliveryStatus;

final class DeliveryProjector
{
    public function project(array $events): array
    {
        usort($events, fn (array $a, array $b): int => [$a['occurred_at'], DeliveryStatus::from($a['type'])->rank()] <=> [$b['occurred_at'], DeliveryStatus::from($b['type'])->rank()]);

        $seen = [];
        $previousRank = -1;
        $terminal = false;
        $conflicts = [];

        foreach ($events as $event) {
            $status = DeliveryStatus::from($event['type']);
            if (isset($seen[$status->value])) {
                $conflicts[] = 'repeated_status';
            }
            if ($terminal || $status->rank() < $previousRank) {
                $conflicts[] = 'incompatible_sequence';
            }
            $seen[$status->value] = $event;
            $previousRank = $status->rank();
            $terminal = $status->isTerminal();
        }

        if (isset($seen['delivered'], $seen['failed'])) {
            $conflicts[] = 'contradictory_terminals';
        }

        $last = $events === [] ? null : $events[array_key_last($events)];
        $normal = ['created', 'assigned', 'picked_up', 'in_transit', 'delivered'];
        $requiredRank = -1;
        foreach ($events as $event) {
            if ($event['type'] !== 'failed') {
                $requiredRank = max($requiredRank, DeliveryStatus::from($event['type'])->rank());
            }
        }
        if (isset($seen['failed'])) {
            $requiredRank = max(0, $requiredRank);
        }
        $missing = array_values(array_diff(array_slice($normal, 0, $requiredRank + 1), array_keys($seen)));
        $quality = $conflicts !== [] ? 'conflict' : ($missing !== [] || $events === [] ? 'pending' : 'consistent');

        return [
            'status' => $quality === 'conflict' ? null : ($last['type'] ?? null),
            'quality' => $quality,
            'issues' => array_values(array_unique([...$conflicts, ...array_map(fn (string $type): string => 'missing_'.$type, $missing)])),
            'courier_id' => isset($seen['assigned']) && count(array_filter($events, fn (array $event): bool => $event['type'] === 'assigned')) === 1 ? $seen['assigned']['data']['courier_id'] : null,
            'delivery_address' => $seen['created']['data']['planned_destination']['label'] ?? null,
            'planned_pickup' => $seen['created']['data']['planned_pickup'] ?? null,
            'planned_destination' => $seen['created']['data']['planned_destination'] ?? null,
            'created_occurred_at' => $seen['created']['occurred_at'] ?? null,
            'completed_at' => $quality === 'consistent' && $terminal ? $last['occurred_at'] : null,
            'by_type' => $seen,
        ];
    }
}
