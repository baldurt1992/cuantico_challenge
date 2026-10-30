<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final class EventNormalizer
{
    public function normalize(array $payload): array
    {
        $data = match ($payload['type']) {
            'created' => $this->createdData($payload['data']),
            'assigned' => ['courier_id' => (int) $payload['data']['courier_id']],
            'picked_up', 'delivered' => [
                'latitude' => (float) $payload['data']['latitude'],
                'longitude' => (float) $payload['data']['longitude'],
            ],
            default => [],
        };

        return [
            'event_id' => strtolower($payload['event_id']),
            'delivery_id' => strtolower($payload['delivery_id']),
            'merchant_id' => (int) $payload['merchant_id'],
            'type' => $payload['type'],
            'occurred_at' => CarbonImmutable::parse($payload['occurred_at'])->utc()->format('Y-m-d H:i:s.u'),
            'data' => $data,
        ];
    }

    private function createdData(array $data): array
    {
        $points = [];
        foreach (['planned_pickup', 'planned_destination'] as $key) {
            $points[$key] = [
                'label' => trim($data[$key]['label']),
                'latitude' => (float) $data[$key]['latitude'],
                'longitude' => (float) $data[$key]['longitude'],
            ];
        }

        return $points;
    }

    public function fingerprint(array $normalized): string
    {
        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
