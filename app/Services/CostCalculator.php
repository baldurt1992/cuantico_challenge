<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class CostCalculator
{
    public function distanceMeters(array $pickup, array $destination): int
    {
        $latitudeDifference = deg2rad($destination['latitude'] - $pickup['latitude']);
        $longitudeDifference = deg2rad($destination['longitude'] - $pickup['longitude']);
        $arc = sin($latitudeDifference / 2) ** 2
            + cos(deg2rad($pickup['latitude'])) * cos(deg2rad($destination['latitude'])) * sin($longitudeDifference / 2) ** 2;

        return (int) round(6371000 * 2 * asin(sqrt(min(1.0, max(0.0, $arc)))), 0, PHP_ROUND_HALF_UP);
    }

    public function calculate(array $projection, array $tariff): ?array
    {
        if ($projection['quality'] !== 'consistent' || ! in_array($projection['status'], ['delivered', 'failed'], true)) {
            return null;
        }

        foreach (['base_minor', 'per_km_minor'] as $field) {
            if (! is_int($tariff[$field]) || $tariff[$field] < 0 || $tariff[$field] > 1000000000) {
                throw new InvalidArgumentException('Tarifa fuera de los límites permitidos.');
            }
        }

        $failed = $projection['status'] === 'failed';
        $meters = $failed ? null : $this->distanceMeters($projection['by_type']['picked_up']['data'], $projection['by_type']['delivered']['data']);
        $distance = $failed ? 0 : $this->roundedDivision($meters * $tariff['per_km_minor'], 1000);
        $subtotal = $tariff['base_minor'] + $distance;
        $local = CarbonImmutable::parse($projection['completed_at'], 'UTC')->setTimezone($tariff['timezone']);
        $night = ! $failed && ($local->hour >= 20 || $local->hour < 6);
        $nightAmount = $night ? $this->roundedDivision($subtotal * 15, 100) : 0;
        $total = $failed ? $this->roundedDivision($tariff['base_minor'] * 40, 100) : $subtotal + $nightAmount;

        return [
            'rules_version' => '1',
            'tariff_id' => $tariff['id'],
            'currency' => 'COP',
            'base_minor' => $tariff['base_minor'],
            'per_km_minor' => $tariff['per_km_minor'],
            'distance_meters' => $meters,
            'distance_minor' => $distance,
            'subtotal_minor' => $failed ? null : $subtotal,
            'night' => $night,
            'night_percent' => $night ? 15 : 0,
            'night_minor' => $nightAmount,
            'failed_percent' => $failed ? 40 : null,
            'completed_at' => $projection['completed_at'],
            'local_completed_at' => $local->toIso8601String(),
            'timezone' => $tariff['timezone'],
            'pickup' => $projection['by_type']['picked_up']['data'] ?? null,
            'destination' => $projection['by_type']['delivered']['data'] ?? null,
            'total_minor' => $total,
        ];
    }

    private function roundedDivision(int $numerator, int $denominator): int
    {
        return intdiv($numerator, $denominator) + (($numerator % $denominator) * 2 >= $denominator ? 1 : 0);
    }
}
