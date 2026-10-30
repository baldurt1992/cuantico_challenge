<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class EventIngestor
{
    public function __construct(
        private EventNormalizer $normalizer,
        private DeliveryProjector $projector,
        private CostCalculator $calculator,
    ) {}

    public function ingest(User $user, array $payload): array
    {
        $merchant = Merchant::findOrFail($payload['merchant_id']);
        Gate::forUser($user)->authorize('ingest', $merchant);
        $normalized = $this->normalizer->normalize($payload);
        $fingerprint = $this->normalizer->fingerprint($normalized);

        return DB::transaction(function () use ($user, $merchant, $normalized, $fingerprint): array {
            $existing = DeliveryEvent::where('emitter_id', $user->id)->where('event_id', $normalized['event_id'])->first();
            if ($existing !== null) {
                abort_unless($existing->delivery->merchant_id === $merchant->id, 404);
                if (! hash_equals($existing->fingerprint, $fingerprint)) {
                    Log::notice('event_id_conflict', ['user_id' => $user->id, 'event_id' => $normalized['event_id']]);
                    abort(409, 'El identificador ya existe con contenido diferente.');
                }

                return ['duplicate' => true, 'delivery' => $existing->delivery];
            }

            $delivery = Delivery::find($normalized['delivery_id']);
            if ($delivery !== null) {
                abort_unless($delivery->merchant_id === $merchant->id, 404);
            } else {
                $delivery = Delivery::create(['id' => $normalized['delivery_id'], 'merchant_id' => $merchant->id]);
            }
            $before = $delivery->only(['status', 'quality', 'issues', 'courier_id', 'delivery_address', 'planned_pickup', 'planned_destination', 'created_occurred_at', 'completed_at', 'cost_minor', 'calculation']);
            $event = $delivery->events()->create([
                'emitter_id' => $user->id,
                'event_id' => $normalized['event_id'],
                'type' => $normalized['type'],
                'occurred_at' => $normalized['occurred_at'],
                'received_at' => now('UTC')->format('Y-m-d H:i:s.u'),
                'data' => $normalized['data'],
                'fingerprint' => $fingerprint,
            ]);
            $events = $delivery->events()->get()->toArray();
            $projection = $this->projector->project($events);
            $tariff = $projection['created_occurred_at'] === null ? null : $merchant->tariffs()
                ->where('effective_from', '<=', $projection['created_occurred_at'])
                ->orderByDesc('effective_from')->first();
            $calculation = $tariff === null ? null : $this->calculator->calculate($projection, $tariff->toArray());
            $issues = $projection['issues'];
            if ($tariff === null && $projection['created_occurred_at'] !== null) {
                $issues[] = 'missing_tariff';
            }
            $delivery->fill([
                ...array_diff_key($projection, array_flip(['by_type', 'issues'])),
                'issues' => $issues,
                'cost_minor' => $calculation['total_minor'] ?? null,
                'calculation' => $calculation,
            ])->save();
            $after = $delivery->only(array_keys($before));
            if ($before !== $after) {
                $delivery->revisions()->create([
                    'event_id' => $event->id,
                    'reason' => $projection['quality'] === 'conflict' ? 'conflict_detected' : 'event_received',
                    'snapshot' => [...$after, 'source_events' => array_column($events, 'event_id')],
                ]);
            }

            return ['duplicate' => false, 'delivery' => $delivery];
        }, 5);
    }
}
